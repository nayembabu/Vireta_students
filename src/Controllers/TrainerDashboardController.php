<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Routine;
use App\Models\User;
use App\Support\BasePath;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class TrainerDashboardController
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
        $horizon = (new DateTimeImmutable('+7 days'))->format('Y-m-d');

        // ---- Today's classes (across all batches) ----
        $todayClasses = Routine::with(['batch', 'course'])
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
                ];
            })
            ->values()
            ->all();

        // ---- Upcoming classes (next 7 days) ----
        $upcoming = Routine::with(['batch', 'course'])
            ->where('session_date', '>', $today)
            ->where('session_date', '<=', $horizon)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get()
            ->map(static function (Routine $r): array {
                return [
                    'id' => $r->id,
                    'session_date' => $r->session_date?->format('Y-m-d'),
                    'batch' => $r->batch?->name,
                    'course' => $r->course?->title,
                    'topic' => $r->topic,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                ];
            })
            ->values()
            ->all();

        // ---- Ungraded submissions ----
        $ungraded = AssignmentSubmission::with(['assignment.course', 'user'])
            ->whereNull('score')
            ->where('status', 'submitted')
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get()
            ->map(static function (AssignmentSubmission $s): array {
                return [
                    'id' => $s->id,
                    'student' => $s->user?->name,
                    'assignment' => $s->assignment?->title,
                    'course' => $s->assignment?->course?->title,
                    'version' => $s->version,
                    'submitted_at' => $s->submitted_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->values()
            ->all();

        $ungradedCount = AssignmentSubmission::whereNull('score')
            ->where('status', 'submitted')
            ->count();

        // ---- Students in trainer's batch (if assigned) ----
        $studentCount = 0;
        if ($user->batch_id !== null) {
            $studentCount = User::where('batch_id', $user->batch_id)
                ->whereHas('role', fn ($q) => $q->where('slug', 'student'))
                ->count();
        }

        // ---- Attendance summary + recent records ----
        $attendance = Attendance::orderByDesc('session_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $attSummary = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
        foreach ($attendance as $a) {
            if (isset($attSummary[$a->status])) {
                $attSummary[$a->status]++;
            }
        }
        $attTotal = $attSummary['present'] + $attSummary['late'] + $attSummary['absent'];
        $attSummary['total'] = $attTotal;
        $attSummary['percentage'] = $attTotal > 0
            ? (int)round(($attSummary['present'] + $attSummary['late']) / $attTotal * 100)
            : 0;

        $recentAttendance = $attendance->map(static function (Attendance $a): array {
            return [
                'id' => $a->id,
                'student' => $a->user?->name,
                'course' => $a->course?->title,
                'session_date' => $a->session_date instanceof \DateTimeInterface ? $a->session_date->format('Y-m-d') : $a->session_date,
                'status' => $a->status,
                'check_in_time' => $a->check_in_time,
            ];
        })->values()->all();

        // ---- Assignments list ----
        $assignments = Assignment::with(['course', 'submissions'])
            ->orderByDesc('due_date')
            ->limit(10)
            ->get()
            ->map(static function (Assignment $a): array {
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'course' => $a->course?->title,
                    'due_date' => $a->due_date?->format('Y-m-d'),
                    'max_score' => $a->max_score,
                    'submission_count' => $a->submissions->count(),
                    'ungraded_count' => $a->submissions->whereNull('score')->count(),
                ];
            })
            ->values()
            ->all();

        // ---- Batch-wise students view ----
        $students = User::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->orderBy('name')
            ->get();

        $batchGroups = [];
        foreach ($students as $s) {
            $key = $s->batch?->name ?? 'No batch';
            $batchGroups[$key][] = [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
            ];
        }

        return $this->render($response, 'trainer/dashboard/index.html.twig', [
            'trainer' => [
                'name' => $user->name,
                'batch' => $user->batch?->name,
                'today_label' => (new DateTimeImmutable())->format('d M Y'),
            ],
            'stats' => [
                'today_classes' => count($todayClasses),
                'ungraded' => $ungradedCount,
                'students' => $studentCount,
                'upcoming' => count($upcoming),
            ],
            'today_classes' => $todayClasses,
            'upcoming' => $upcoming,
            'ungraded' => $ungraded,
            'attendance' => [
                'summary' => $attSummary,
                'recent' => $recentAttendance,
            ],
            'assignments' => $assignments,
            'batch_groups' => $batchGroups,
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
