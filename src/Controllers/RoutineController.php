<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Routine;
use App\Services\AttendanceService;
use App\Support\BasePath;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class RoutineController
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

        $batchId = $user->batch_id;

        if ($batchId === null) {
            return $this->render($response, 'routine/index.html.twig', [
                'batch' => null,
                'upcoming' => [],
                'past' => [],
            ]);
        }

        // mentor is a nullable belongsTo relation; resolved lazily.
        $routines = Routine::with(['course'])
            ->where('batch_id', $batchId)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $today = (new DateTimeImmutable())->format('Y-m-d');

        $upcoming = [];
        $past = [];
        foreach ($routines as $routine) {
            $dateKey = $routine->session_date?->format('Y-m-d');

            $item = [
                'session_date' => $dateKey,
                'start_time' => $routine->start_time,
                'end_time' => $routine->end_time,
                'topic' => $routine->topic,
                'room' => $routine->room,
                'meeting_link' => $routine->meeting_link,
                'course' => $routine->course?->title,
                'mentor' => $routine->mentor?->name,
                'is_today' => $dateKey === $today,
            ];

            if ($dateKey !== null && $dateKey < $today) {
                $past[] = $item;
            } else {
                $upcoming[] = $item;
            }
        }

        return $this->render($response, 'routine/index.html.twig', [
            'batch' => $user->batch()->first(),
            'upcoming' => $upcoming,
            'past' => $past,
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