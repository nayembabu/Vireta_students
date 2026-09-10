<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Attendance;
use App\Models\Routine;
use App\Services\AttendanceService;
use App\Support\BasePath;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AttendanceController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly AttendanceService $attendanceService
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $this->attendanceService->captureToday($user);

        // course & routine are nullable belongsTo relations; resolved lazily to
        // avoid the PHP 8.5 "null array offset" deprecation in Eloquent.
        $records = Attendance::where('user_id', $user->id)
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get()
            ->map(static function (Attendance $a): array {
                return [
                    'session_date' => $a->session_date instanceof \DateTimeInterface ? $a->session_date->format('Y-m-d') : $a->session_date,
                    'status' => $a->status,
                    'check_in_time' => $a->check_in_time,
                    'course' => $a->course?->title,
                    'start_time' => $a->routine?->start_time,
                    'end_time' => $a->routine?->end_time,
                    'topic' => $a->routine?->topic,
                ];
            })
            ->values()
            ->all();

        $grouped = [];
        foreach ($records as $record) {
            $key = $record['course'] !== null ? $record['course'] : 'Other';
            $grouped[$key][] = $record;
        }

        $today = (new DateTimeImmutable())->format('Y-m-d');

        $upcoming = [];
        if ($user->batch_id !== null) {
            // mentor is a nullable belongsTo relation; resolved lazily.
            $upcoming = Routine::with(['course'])
                ->where('batch_id', $user->batch_id)
                ->where('session_date', '>', $today)
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get()
                ->map(static function (Routine $r): array {
                    return [
                        'session_date' => $r->session_date !== null ? $r->session_date->format('Y-m-d') : null,
                        'start_time' => $r->start_time,
                        'end_time' => $r->end_time,
                        'topic' => $r->topic,
                        'course' => $r->course?->title,
                        'mentor' => $r->mentor?->name,
                        'room' => $r->room,
                    ];
                })
                ->values()
                ->all();
        }

        $summary = [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'excused' => 0,
        ];
        foreach ($records as $record) {
            if (isset($summary[$record['status']])) {
                $summary[$record['status']]++;
            }
        }

        $total = $summary['present'] + $summary['late'] + $summary['absent'];
        $summary['total'] = $total;
        $summary['percentage'] = $total > 0 ? (int)round(($summary['present'] + $summary['late']) / $total * 100) : 0;

        return $this->render($response, 'attendance/index.html.twig', [
            'summary' => $summary,
            'grouped' => $grouped,
            'upcoming' => $upcoming,
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