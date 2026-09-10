<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Support\BasePath;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class AdminAssignmentController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $courseId = (int)($request->getQueryParams()['course'] ?? 0);
        $onlyUngraded = (bool)($request->getQueryParams()['ungraded'] ?? false);

        $query = Assignment::with([
            'course',
            'submissions' => function ($q) {
                $q->with(['user'])->orderByDesc('version');
            },
        ]);

        if ($courseId > 0) {
            $query->where('course_id', $courseId);
        }

        $assignments = $query->orderByDesc('due_date')->get();

        $items = [];
        foreach ($assignments as $assignment) {
            $latest = $assignment->submissions->first();

            $submissions = $assignment->submissions->map(static function (AssignmentSubmission $s): array {
                return [
                    'id' => $s->id,
                    'student' => $s->user?->name,
                    'version' => $s->version,
                    'submitted_at' => $s->submitted_at?->format('Y-m-d H:i:s'),
                    'score' => $s->score,
                    'status' => $s->status,
                ];
            })->values()->all();

            if ($onlyUngraded && $latest !== null && $latest->score !== null) {
                continue;
            }

            $items[] = [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'course' => $assignment->course?->title,
                'due_date' => $assignment->due_date?->format('Y-m-d'),
                'max_score' => $assignment->max_score,
                'submission_count' => $assignment->submissions->count(),
                'ungraded_count' => $assignment->submissions->whereNull('score')->count(),
                'latest' => $latest !== null ? [
                    'id' => $latest->id,
                    'student' => $latest->user?->name,
                    'version' => $latest->version,
                    'score' => $latest->score,
                ] : null,
                'submissions' => $submissions,
            ];
        }

        return $this->render($response, 'admin/assignments/index.html.twig', [
            'items' => $items,
            'courses' => Course::orderBy('title')->get(),
            'course_id' => $courseId,
            'ungraded' => $onlyUngraded,
        ]);
    }

    public function gradeForm(Request $request, Response $response): Response
    {
        $submission = $this->find($request);

        if ($submission === null) {
            return $this->render404($response, 'Submission not found.');
        }

        $submission->load(['assignment.course', 'user']);

        return $this->render($response, 'admin/assignments/grade.html.twig', [
            'submission' => [
                'id' => $submission->id,
                'version' => $submission->version,
                'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i:s'),
                'text' => $submission->submission_text,
                'file_path' => $submission->file_path,
                'score' => $submission->score,
                'feedback' => $submission->feedback,
                'status' => $submission->status,
                'student' => $submission->user?->name,
                'assignment_title' => $submission->assignment?->title,
                'course' => $submission->assignment?->course?->title,
                'max_score' => $submission->assignment?->max_score,
            ],
            'old' => [
                'score' => $submission->score ?? '',
                'feedback' => $submission->feedback ?? '',
            ],
            'errors' => ['score' => '', 'feedback' => ''],
        ]);
    }

    public function grade(Request $request, Response $response): Response
    {
        $submission = $this->find($request);

        if ($submission === null) {
            return $this->render404($response, 'Submission not found.');
        }

        $submission->load(['assignment']);

        $body = $request->getParsedBody();
        $score = trim((string)($body['score'] ?? ''));
        $feedback = trim((string)($body['feedback'] ?? ''));

        $maxScore = $submission->assignment?->max_score ?? 100;

        $errors = [];
        if ($score === '' || !is_numeric($score)) {
            $errors['score'] = 'Please enter a numeric score.';
        } elseif ((float)$score < 0 || (float)$score > $maxScore) {
            $errors['score'] = 'Score must be between 0 and ' . $maxScore . '.';
        }

        if ($errors !== []) {
            $submission->load(['assignment.course', 'user']);
            return $this->render($response, 'admin/assignments/grade.html.twig', [
                'submission' => [
                    'id' => $submission->id,
                    'version' => $submission->version,
                    'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i:s'),
                    'text' => $submission->submission_text,
                    'file_path' => $submission->file_path,
                    'score' => $submission->score,
                    'feedback' => $submission->feedback,
                    'status' => $submission->status,
                    'student' => $submission->user?->name,
                    'assignment_title' => $submission->assignment?->title,
                    'course' => $submission->assignment?->course?->title,
                    'max_score' => $maxScore,
                ],
                'old' => ['score' => $score, 'feedback' => $feedback],
                'errors' => array_merge(['score' => '', 'feedback' => ''], $errors),
            ]);
        }

        $submission->update([
            'score' => (int)round((float)$score),
            'feedback' => $feedback !== '' ? $feedback : null,
            'status' => 'graded',
            'graded_by' => $this->auth->user()->id,
            'graded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash->success('Submission graded successfully.');

        return $this->redirect('/admin/assignments');
    }

    private function find(Request $request): ?AssignmentSubmission
    {
        return AssignmentSubmission::find($this->routeId($request));
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
