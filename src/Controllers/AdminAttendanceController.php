<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Routine;
use App\Models\User;
use App\Support\BasePath;
use App\Support\Flash;
use App\Support\StaffRedirectTrait;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class AdminAttendanceController
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
        $batchId = (int)($request->getQueryParams()['batch'] ?? 0);
        $date = trim((string)($request->getQueryParams()['date'] ?? ''));
        $status = trim((string)($request->getQueryParams()['status'] ?? ''));

        // NOTE: course & routine are nullable belongsTo relations. Eager loading
        // them triggers a PHP 8.5 "null array offset" deprecation in Eloquent,
        // so they are resolved lazily (safe when the FK is null).
        $query = Attendance::with(['user']);

        if ($batchId > 0) {
            $query->whereHas('user', fn ($q) => $q->where('batch_id', $batchId));
        }
        if ($date !== '' && DateTimeImmutable::createFromFormat('Y-m-d', $date)) {
            $query->where('session_date', $date);
        }
        if ($status !== '' && in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
            $query->where('status', $status);
        }

        $records = $query->orderByDesc('session_date')->orderByDesc('id')->get();

        $items = $records->map(static function (Attendance $a): array {
            return [
                'id' => $a->id,
                'student' => $a->user?->name,
                'course' => $a->course?->title,
                'topic' => $a->routine?->topic,
                'session_date' => $a->session_date instanceof \DateTimeInterface ? $a->session_date->format('Y-m-d') : $a->session_date,
                'status' => $a->status,
                'check_in_time' => $a->check_in_time,
            ];
        })->values()->all();

        return $this->render($response, 'admin/attendance/index.html.twig', [
            'items' => $items,
            'batches' => Batch::orderBy('name')->get(),
            'filters' => ['batch' => $batchId, 'date' => $date, 'status' => $status],
        ]);
    }

    public function override(Request $request, Response $response): Response
    {
        $id = $this->routeId($request);
        $record = Attendance::find($id);

        if ($record === null) {
            return $this->render404($response, 'Attendance record not found.');
        }

        $body = $request->getParsedBody();
        $status = trim((string)($body['status'] ?? ''));
        $checkIn = trim((string)($body['check_in_time'] ?? ''));

        if (!in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
            $this->flash->error('Invalid attendance status.');
            return $this->staffRedirect($request, '/attendance');
        }

        $record->update([
            'status' => $status,
            'check_in_time' => $checkIn !== '' ? $checkIn : null,
            'marked_by' => $this->auth->user()->id,
        ]);

        $this->flash->success('Attendance updated.');

        return $this->staffRedirect($request, '/attendance');
    }

    public function markForm(Request $request, Response $response): Response
    {
        $batchId = (int)($request->getQueryParams()['batch'] ?? 0);
        $routineId = (int)($request->getQueryParams()['routine'] ?? 0);

        $routines = [];
        $students = [];
        $routine = null;

        if ($batchId > 0) {
            // mentor is a nullable belongsTo relation; resolved lazily.
            $routines = Routine::with(['course'])
                ->where('batch_id', $batchId)
                ->orderByDesc('session_date')
                ->orderBy('start_time')
                ->get();
        }

        if ($routineId > 0) {
            $routine = Routine::with(['course', 'batch'])->find($routineId);

            if ($routine !== null) {
                $students = User::where('batch_id', $routine->batch_id)
                    ->whereHas('role', fn ($q) => $q->where('slug', 'student'))
                    ->orderBy('name')
                    ->get()
                    ->map(function (User $u) use ($routine): array {
                        $existing = Attendance::where('routine_id', $routine->id)
                            ->where('user_id', $u->id)
                            ->first();

                        return [
                            'user_id' => $u->id,
                            'name' => $u->name,
                            'status' => $existing?->status ?? '',
                            'check_in_time' => $existing?->check_in_time ?? '',
                        ];
                    })
                    ->values()
                    ->all();
            }
        }

        return $this->render($response, 'admin/attendance/mark.html.twig', [
            'batches' => Batch::orderBy('name')->get(),
            'batch_id' => $batchId,
            'routines' => $routines,
            'routine' => $routine,
            'routine_id' => $routineId,
            'students' => $students,
        ]);
    }

    public function mark(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $routineId = (int)($body['routine_id'] ?? 0);
        $routine = Routine::find($routineId);

        if ($routine === null) {
            $this->flash->error('Please select a class session to mark.');
            return $this->staffRedirect($request, '/attendance/mark');
        }

        $statuses = $body['status'] ?? [];
        $checkIns = $body['check_in_time'] ?? [];

        if (!is_array($statuses)) {
            $statuses = [];
        }
        if (!is_array($checkIns)) {
            $checkIns = [];
        }

        $count = (int)($body['count'] ?? 0);

        for ($i = 0; $i < $count; $i++) {
            $userId = (int)($body['user_id'][$i] ?? 0);
            $status = trim((string)($statuses[$i] ?? ''));

            if ($userId <= 0) {
                continue;
            }

            if (!in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                continue;
            }

            $checkIn = trim((string)($checkIns[$i] ?? ''));

            Attendance::updateOrCreate(
                ['routine_id' => $routine->id, 'user_id' => $userId],
                [
                    'course_id' => $routine->course_id,
                    'session_date' => $routine->session_date?->format('Y-m-d'),
                    'status' => $status,
                    'check_in_time' => $checkIn !== '' ? $checkIn : null,
                    'marked_by' => $this->auth->user()->id,
                ]
            );
        }

        $this->flash->success('Attendance saved for this session.');

        return $this->staffRedirect($request, '/attendance/mark?batch=' . $routine->batch_id . '&routine=' . $routine->id);
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
