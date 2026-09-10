<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Routine;
use App\Models\Student;
use App\Models\User;
use App\Support\BasePath;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AdminDashboardController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        $today = (new DateTimeImmutable())->format('Y-m-d');

        // ---- Totals ----
        $totals = [
            'students' => Student::count(),
            'registered' => Student::where('status', 'registered')->count(),
            'pending' => Student::where('status', 'pending')->count(),
            'blocked' => Student::where('status', 'blocked')->count(),
            'batches' => Batch::count(),
            'courses' => Course::count(),
            'staff' => User::whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'trainer', 'cashier']))->count(),
        ];

        // ---- Verify queue ----
        $queue = [
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'ungraded' => AssignmentSubmission::whereNull('score')->where('status', 'submitted')->count(),
        ];

        // ---- Today's classes ----
        // mentor is a nullable belongsTo relation; resolved lazily.
        $classes = Routine::with(['batch', 'course'])
            ->where('session_date', $today)
            ->orderBy('start_time')
            ->get()
            ->map(static function (Routine $r): array {
                return [
                    'id' => $r->id,
                    'batch' => $r->batch?->name,
                    'course' => $r->course?->title,
                    'topic' => $r->topic,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                    'room' => $r->room,
                    'mentor' => $r->mentor?->name,
                ];
            })
            ->values()
            ->all();

        // ---- Recent students (latest registered first) ----
        $recentStudents = Student::with(['batch', 'user'])
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(static function (Student $s): array {
                return [
                    'id' => $s->id,
                    'name' => $s->name ?? '—',
                    'reg_no' => $s->educational_registration_no,
                    'batch' => $s->batch?->name,
                    'status' => $s->status,
                    'registered' => $s->user !== null,
                ];
            })
            ->values()
            ->all();

        // ---- Recent payments ----
        $recentPayments = Payment::with(['user', 'course'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(static function (Payment $p): array {
                return [
                    'id' => $p->id,
                    'student' => $p->user?->name,
                    'course' => $p->course?->title,
                    'trx_id' => $p->trx_id,
                    'amount' => (float)$p->amount,
                    'status' => $p->status,
                    'date' => $p->created_at instanceof \DateTimeInterface ? $p->created_at->format('Y-m-d H:i') : null,
                ];
            })
            ->values()
            ->all();

        return $this->render($response, 'admin/dashboard/index.html.twig', [
            'admin' => [
                'name' => $user->name,
                'role' => $user->role?->name,
                'today_label' => (new DateTimeImmutable())->format('d M Y'),
            ],
            'totals' => $totals,
            'queue' => $queue,
            'classes' => $classes,
            'recent_students' => $recentStudents,
            'recent_payments' => $recentPayments,
        ]);
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
