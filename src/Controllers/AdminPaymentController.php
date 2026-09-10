<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourse;
use App\Support\BasePath;
use App\Support\Flash;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class AdminPaymentController
{
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
        return $this->render($response, 'admin/payments/create.html.twig', [
            'students' => $this->students(),
            'courses' => Course::orderBy('title')->get(),
            'old' => $this->emptyOld(),
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $old = [
            'user_id' => (int)($body['user_id'] ?? 0),
            'course_id' => (int)($body['course_id'] ?? 0),
            'trx_id' => trim((string)($body['trx_id'] ?? '')),
            'sender_number' => trim((string)($body['sender_number'] ?? '')),
            'amount' => trim((string)($body['amount'] ?? '')),
            'note' => trim((string)($body['note'] ?? '')),
        ];

        $errors = $this->validateCreate($old);

        if ($errors !== []) {
            return $this->render($response, 'admin/payments/create.html.twig', [
                'students' => $this->students(),
                'courses' => Course::orderBy('title')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
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

        return $this->redirect('/admin/payments?status=verified');
    }

    public function verify(Request $request, Response $response): Response
    {
        $payment = $this->find($request);

        if ($payment === null) {
            return $this->render404($response, 'Payment not found.');
        }

        if ($payment->status !== 'pending') {
            $this->flash->info('This payment has already been processed.');
            return $this->redirect('/admin/payments');
        }

        $payment->update([
            'status' => 'verified',
            'verified_by' => $this->auth->user()->id,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash->success('Payment verified and added to the student balance.');

        return $this->redirect('/admin/payments');
    }

    public function reject(Request $request, Response $response): Response
    {
        $payment = $this->find($request);

        if ($payment === null) {
            return $this->render404($response, 'Payment not found.');
        }

        if ($payment->status !== 'pending') {
            $this->flash->info('This payment has already been processed.');
            return $this->redirect('/admin/payments');
        }

        $payment->update([
            'status' => 'rejected',
            'verified_by' => $this->auth->user()->id,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash->info('Payment rejected.');

        return $this->redirect('/admin/payments');
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
            return $this->redirect('/admin/payments');
        }

        UserCourse::where('user_id', $payment->user_id)
            ->where('course_id', $payment->course_id)
            ->update(['fee_deadline' => $deadline]);

        $this->flash->success('Payment deadline updated.');

        return $this->redirect('/admin/payments');
    }

    private function students(): array
    {
        return User::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->orderBy('name')
            ->get()
            ->all();
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
