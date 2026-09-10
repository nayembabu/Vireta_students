<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourse;
use App\Support\BasePath;
use App\Support\Flash;
use App\Support\StaffRedirectTrait;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class AdminPaymentController
{
    use StaffRedirectTrait;

    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $status = trim((string)($request->getQueryParams()['status'] ?? 'pending'));

        // user & course are nullable belongsTo relations; resolved lazily to
        // avoid the PHP 8.5 "null array offset" deprecation in Eloquent.
        $query = Payment::query();

        if ($status !== '' && in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $payments = $query->orderByDesc('created_at')->get();

        $items = $payments->map(static function (Payment $p): array {
            return [
                'id' => $p->id,
                'student' => $p->user?->name,
                'course' => $p->course?->title,
                'trx_id' => $p->trx_id,
                'sender_number' => $p->sender_number,
                'amount' => (float)$p->amount,
                'screenshot' => $p->screenshot,
                'note' => $p->note,
                'status' => $p->status,
                'date' => $p->created_at instanceof \DateTimeInterface ? $p->created_at->format('Y-m-d H:i') : null,
            ];
        })->values()->all();

        return $this->render($response, 'admin/payments/index.html.twig', [
            'items' => $items,
            'status' => $status,
            'counts' => [
                'pending' => Payment::where('status', 'pending')->count(),
                'verified' => Payment::where('status', 'verified')->count(),
                'rejected' => Payment::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->renderCreate($response, $this->emptyOld(), $this->emptyErrors());
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $old = [
            'user_id' => (int)($body['user_id'] ?? 0),
            'course_id' => (int)($body['course_id'] ?? 0),
            'batch_id' => (int)($body['batch_id'] ?? 0),
            'trx_id' => trim((string)($body['trx_id'] ?? '')),
            'sender_number' => trim((string)($body['sender_number'] ?? '')),
            'amount' => trim((string)($body['amount'] ?? '')),
            'note' => trim((string)($body['note'] ?? '')),
        ];

        $errors = $this->validateCreate($old);

        if ($errors !== []) {
            return $this->renderCreate($response, $old, $errors);
        }

        Payment::create([
            'user_id' => $old['user_id'],
            'course_id' => $old['course_id'],
            'trx_id' => $old['trx_id'],
            'sender_number' => $old['sender_number'] !== '' ? $old['sender_number'] : null,
            'amount' => (float)$old['amount'],
            'note' => $old['note'] !== '' ? $old['note'] : null,
            'status' => 'verified',
            'verified_by' => $this->auth->user()->id,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash->success('Payment recorded and added to the student balance.');

        return $this->staffRedirect($request, '/payments?status=verified');
    }

    public function verify(Request $request, Response $response): Response
    {
        $payment = $this->find($request);

        if ($payment === null) {
            return $this->render404($response, 'Payment not found.');
        }

        if ($payment->status !== 'pending') {
            $this->flash->info('This payment has already been processed.');
            return $this->staffRedirect($request, '/payments');
        }

        $payment->update([
            'status' => 'verified',
            'verified_by' => $this->auth->user()->id,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash->success('Payment verified and added to the student balance.');

        return $this->staffRedirect($request, '/payments');
    }

    public function reject(Request $request, Response $response): Response
    {
        $payment = $this->find($request);

        if ($payment === null) {
            return $this->render404($response, 'Payment not found.');
        }

        if ($payment->status !== 'pending') {
            $this->flash->info('This payment has already been processed.');
            return $this->staffRedirect($request, '/payments');
        }

        $payment->update([
            'status' => 'rejected',
            'verified_by' => $this->auth->user()->id,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash->info('Payment rejected.');

        return $this->staffRedirect($request, '/payments');
    }

    public function deadline(Request $request, Response $response): Response
    {
        $payment = $this->find($request);

        if ($payment === null) {
            return $this->render404($response, 'Payment not found.');
        }

        $body = $request->getParsedBody();
        $deadline = trim((string)($body['fee_deadline'] ?? ''));

        if ($deadline === '' || !DateTimeImmutable::createFromFormat('Y-m-d', $deadline)) {
            $this->flash->error('Please enter a valid deadline date.');
            return $this->staffRedirect($request, '/payments');
        }

        UserCourse::where('user_id', $payment->user_id)
            ->where('course_id', $payment->course_id)
            ->update(['fee_deadline' => $deadline]);

        $this->flash->success('Payment deadline updated.');

        return $this->staffRedirect($request, '/payments');
    }

    private function renderCreate(Response $response, array $old, array $errors): Response
    {
        $courseId = (int)($old['course_id'] ?? 0);
        $batchId = (int)($old['batch_id'] ?? 0);

        return $this->render($response, 'admin/payments/create.html.twig', [
            'courses' => Course::orderBy('title')->get(),
            'batches' => $courseId > 0
                ? Batch::where('course_id', $courseId)->orderBy('name')->get()
                : collect(),
            'students' => $batchId > 0
                ? $this->studentsInBatch($batchId)
                : collect(),
            'old' => $old,
            'errors' => array_merge($this->emptyErrors(), $errors),
        ]);
    }

    /** HTMX: options for the batch dropdown of a given course. */
    public function batches(Request $request, Response $response): Response
    {
        $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);
        $batches = $courseId > 0
            ? Batch::where('course_id', $courseId)->orderBy('name')->get()
            : collect();

        $base = BasePath::detect($_SERVER);
        $html = '<select name="batch_id" '
            . 'hx-get="' . $base . '/admin/payments/students" '
            . 'hx-target="#student-select" hx-swap="innerHTML" '
            . 'class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-400 focus:outline-none">';
        $html .= '<option value="">Select batch</option>';
        foreach ($batches as $b) {
            $html .= '<option value="' . $b->id . '">' . htmlspecialchars((string)$b->name, ENT_QUOTES) . '</option>';
        }
        $html .= '</select>';

        $response->getBody()->write($html);
        return $response;
    }

    /** HTMX: options for the student dropdown of a given batch. */
    public function students(Request $request, Response $response): Response
    {
        $batchId = (int)($request->getQueryParams()['batch_id'] ?? 0);
        $students = $batchId > 0 ? $this->studentsInBatch($batchId) : collect();

        $base = BasePath::detect($_SERVER);
        $html = '<select name="user_id" '
            . 'hx-get="' . $base . '/admin/payments/fee-summary" '
            . 'hx-target="#fee-summary" hx-swap="innerHTML" hx-include="closest form" '
            . 'class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-teal-400 focus:outline-none">';
        $html .= '<option value="">Select student</option>';
        foreach ($students as $s) {
            $html .= '<option value="' . $s->id . '">' . htmlspecialchars((string)$s->name, ENT_QUOTES) . '</option>';
        }
        $html .= '</select>';

        $response->getBody()->write($html);
        return $response;
    }

    /** HTMX: fee summary (total / paid / due) for the selected student + course. */
    public function feeSummary(Request $request, Response $response): Response
    {
        $userId = (int)($request->getQueryParams()['user_id'] ?? 0);
        $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);

        $course = $courseId > 0 ? Course::find($courseId) : null;
        $user = $userId > 0 ? User::find($userId) : null;

        if ($course === null || $user === null) {
            $response->getBody()->write('<p class="text-xs text-slate-400">Select a student to see the fee summary.</p>');
            return $response;
        }

        $fee = (float)$course->fee;
        $paid = (float)Payment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('status', 'verified')
            ->sum('amount');
        $due = max(0, $fee - $paid);
        $fullPaid = $due <= 0;

        $html = '<div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-center">'
            . '<div><div class="text-[11px] font-bold uppercase text-slate-400">Total fee</div>'
            . '<div class="font-bold text-slate-800">৳ ' . number_format($fee, 0) . '</div></div>'
            . '<div><div class="text-[11px] font-bold uppercase text-teal-600">Paid</div>'
            . '<div class="font-bold text-teal-600">৳ ' . number_format($paid, 0) . '</div></div>'
            . '<div><div class="text-[11px] font-bold uppercase ' . ($fullPaid ? 'text-teal-600' : 'text-rose-500') . '">Due</div>'
            . '<div class="font-bold ' . ($fullPaid ? 'text-teal-600' : 'text-rose-600') . '">৳ ' . number_format($due, 0) . '</div></div>'
            . '</div>';

        if ($fullPaid) {
            $html .= '<p class="mt-1 text-center text-xs font-semibold text-teal-600">✅ Fee complete</p>';
        }

        $response->getBody()->write($html);
        return $response;
    }

    private function studentsInBatch(int $batchId)
    {
        return User::where('batch_id', $batchId)
            ->whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->orderBy('name')
            ->get();
    }

    private function validateCreate(array $old): array
    {
        $errors = [];

        if ($old['user_id'] <= 0 || !User::where('id', $old['user_id'])->exists()) {
            $errors['user_id'] = 'Please select a student.';
        }
        if ($old['course_id'] <= 0 || !Course::where('id', $old['course_id'])->exists()) {
            $errors['course_id'] = 'Please select a course.';
        }
        if ($old['trx_id'] === '') {
            $errors['trx_id'] = 'Please enter a payment number (TRX ID).';
        } elseif (!preg_match('/^[A-Za-z0-9]{4,60}$/', $old['trx_id'])) {
            $errors['trx_id'] = 'Please enter a valid payment number.';
        } elseif (Payment::where('trx_id', $old['trx_id'])->exists()) {
            $errors['trx_id'] = 'This payment number has already been used.';
        }
        if ($old['sender_number'] !== '' && !preg_match('/^01[0-9]{9}$/', $old['sender_number'])) {
            $errors['sender_number'] = 'Please enter a valid sender number (01XXXXXXXXX).';
        }
        if ($old['amount'] === '' || !is_numeric($old['amount']) || (float)$old['amount'] <= 0) {
            $errors['amount'] = 'Please enter a valid amount.';
        }

        return $errors;
    }

    private function emptyErrors(): array
    {
        return [
            'user_id' => '',
            'course_id' => '',
            'batch_id' => '',
            'trx_id' => '',
            'sender_number' => '',
            'amount' => '',
            'note' => '',
        ];
    }

    private function emptyOld(): array
    {
        return [
            'user_id' => '',
            'course_id' => '',
            'batch_id' => '',
            'trx_id' => '',
            'sender_number' => '',
            'amount' => '',
            'note' => '',
        ];
    }

    private function find(Request $request): ?Payment
    {
        return Payment::find($this->routeId($request));
    }

    private function routeId(Request $request): int
    {
        try {
            $route = RouteContext::fromRequest($request)->getRoute();
            $id = (int)$route->getArgument('id');
        } catch (\Throwable) {
            $id = 0;
        }

        return $id;
    }

    private function render404(Response $response, string $title): Response
    {
        return $this->render($response, 'errors/404_message.html.twig', ['message' => $title])->withStatus(404);
    }

    private function render(Response $response, string $template, array $data = []): Response
    {
        return $this->twig->render($response, $template, $data);
    }

    private function redirect(string $url): Response
    {
        $response = (new \Slim\Psr7\Response())->withStatus(302);
        return $response->withHeader('Location', BasePath::detect($_SERVER) . $url);
    }
}
