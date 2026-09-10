<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Support\BasePath;
use App\Support\Flash;
use App\Support\StaffRedirectTrait;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class AdminAssignmentController
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

    public function createForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/assignments/form.html.twig', [
            'assignment' => null,
            'courses' => Course::orderBy('title')->get(),
            'old' => $this->emptyOld(),
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old);

        if ($errors !== []) {
            return $this->render($response, 'admin/assignments/form.html.twig', [
                'assignment' => null,
                'courses' => Course::orderBy('title')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        Assignment::create([
            'course_id' => $old['course_id'],
            'title' => $old['title'],
            'description' => $old['description'] !== '' ? $old['description'] : null,
            'due_date' => $old['due_date'] !== '' ? $old['due_date'] : null,
            'max_score' => $old['max_score'],
        ]);

        $this->flash->success('Assignment created successfully.');

        return $this->staffRedirect($request, '/assignments');
    }

    public function editForm(Request $request, Response $response): Response
    {
        $assignment = $this->findAssignment($request);

        if ($assignment === null) {
            return $this->render404($response, 'Assignment not found.');
        }

        return $this->render($response, 'admin/assignments/form.html.twig', [
            'assignment' => $assignment,
            'courses' => Course::orderBy('title')->get(),
            'old' => [
                'course_id' => (int)$assignment->course_id,
                'title' => $assignment->title,
                'description' => $assignment->description ?? '',
                'due_date' => $assignment->due_date?->format('Y-m-d\TH:i'),
                'max_score' => (int)$assignment->max_score,
            ],
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function edit(Request $request, Response $response): Response
    {
        $assignment = $this->findAssignment($request);

        if ($assignment === null) {
            return $this->render404($response, 'Assignment not found.');
        }

        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old);

        if ($errors !== []) {
            return $this->render($response, 'admin/assignments/form.html.twig', [
                'assignment' => $assignment,
                'courses' => Course::orderBy('title')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        $assignment->update([
            'course_id' => $old['course_id'],
            'title' => $old['title'],
            'description' => $old['description'] !== '' ? $old['description'] : null,
            'due_date' => $old['due_date'] !== '' ? $old['due_date'] : null,
            'max_score' => $old['max_score'],
        ]);

        $this->flash->success('Assignment updated successfully.');

        return $this->staffRedirect($request, '/assignments');
    }

    public function delete(Request $request, Response $response): Response
    {
        $assignment = $this->findAssignment($request);

        if ($assignment === null) {
            return $this->render404($response, 'Assignment not found.');
        }

        $assignment->delete();
        $this->flash->success('Assignment deleted.');

        return $this->staffRedirect($request, '/assignments');
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

        return $this->staffRedirect($request, '/assignments');
    }

    private function findAssignment(Request $request): ?Assignment
    {
        return Assignment::find($this->routeId($request));
    }

    private function normalize(array $data): array
    {
        return [
            'course_id' => (int)($data['course_id'] ?? 0),
            'title' => trim((string)($data['title'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'due_date' => trim((string)($data['due_date'] ?? '')),
            'max_score' => max(1, (int)($data['max_score'] ?? 100)),
        ];
    }

    private function validate(array $old): array
    {
        $errors = [];

        if ($old['course_id'] <= 0 || !Course::where('id', $old['course_id'])->exists()) {
            $errors['course_id'] = 'Please select a course.';
        }
        if ($old['title'] === '') {
            $errors['title'] = 'Assignment title is required.';
        }
        if ($old['due_date'] !== '' && !strtotime($old['due_date'])) {
            $errors['due_date'] = 'Please enter a valid due date.';
        }

        return $errors;
    }

    private function emptyErrors(): array
    {
        return [
            'course_id' => '',
            'title' => '',
            'description' => '',
            'due_date' => '',
            'max_score' => '',
        ];
    }

    private function emptyOld(): array
    {
        return [
            'course_id' => '',
            'title' => '',
            'description' => '',
            'due_date' => '',
            'max_score' => 100,
        ];
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
