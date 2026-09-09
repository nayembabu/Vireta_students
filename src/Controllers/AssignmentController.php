<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\UserCourse;
use App\Support\BasePath;
use App\Support\Flash;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class AssignmentController
{
    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/assignments';

    private const ALLOWED_FILES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'zip' => 'application/zip',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $courseIds = $user->enrolledCourses()->pluck('courses.id');

        $assignments = Assignment::with(['course', 'submissions' => function ($q) use ($user) {
            $q->where('user_id', $user->id)->orderByDesc('version');
        }])
            ->whereIn('course_id', $courseIds)
            ->orderByDesc('due_date')
            ->get();

        $items = [];
        foreach ($assignments as $assignment) {
            $latest = $assignment->submissions->first();
            $state = $this->state($assignment, $latest);

            $items[] = [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'course' => $assignment->course?->title,
                'due_date' => $assignment->due_date?->format('Y-m-d H:i:s'),
                'max_score' => $assignment->max_score,
                'state' => $state['key'],
                'state_label' => $state['label'],
                'version_count' => $assignment->submissions->count(),
                'latest_score' => $latest?->score,
                'can_submit' => $state['can_submit'],
            ];
        }

        return $this->render($response, 'assignments/index.html.twig', ['items' => $items]);
    }

    public function show(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $id = $this->routeId($request);
        $assignment = Assignment::with(['course', 'submissions' => function ($q) use ($user) {
            $q->where('user_id', $user->id)->orderByDesc('version');
        }])->find($id);

        if (!$assignment instanceof Assignment) {
            return $this->render404($response, 'Assignment not found.');
        }

        if (!$this->isEnrolled($user->id, $assignment->course_id)) {
            return $this->render404($response, 'This assignment is not part of your enrolled courses.');
        }

        $latest = $assignment->submissions->first();
        $state = $this->state($assignment, $latest);

        return $this->render($response, 'assignments/show.html.twig', [
            'assignment' => $this->showData($assignment, $latest, $state),
            'errors' => $this->emptyErrors(),
            'old' => ['submission_text' => ''],
        ]);
    }

    public function submit(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $id = $this->routeId($request);
        $assignment = Assignment::with(['submissions' => function ($q) use ($user) {
            $q->where('user_id', $user->id)->orderByDesc('version');
        }])->find($id);

        if (!$assignment instanceof Assignment) {
            return $this->render404($response, 'Assignment not found.');
        }

        if (!$this->isEnrolled($user->id, $assignment->course_id)) {
            return $this->render404($response, 'This assignment is not part of your enrolled courses.');
        }

        $now = new DateTimeImmutable();

        if ($assignment->due_date === null || $assignment->due_date < $now) {
            $this->flash->error('The deadline has passed, submissions are no longer accepted.');
            return $this->redirect('/assignments/' . $assignment->id);
        }

        $latest = $assignment->submissions->first();

        if ($latest !== null && $latest->score !== null) {
            $this->flash->error('This assignment has already been graded and can no longer be changed.');
            return $this->redirect('/assignments/' . $assignment->id);
        }

        $body = $request->getParsedBody();
        $text = trim((string)($body['submission_text'] ?? ''));
        $files = $request->getUploadedFiles();
        /** @var UploadedFileInterface|null $uploaded */
        $uploaded = $files['submission_file'] ?? null;

        $errors = [];
        if ($text === '' && !$this->hasFile($uploaded)) {
            $errors['submission_text'] = 'Provide an answer (text) or a file - at least one is required.';
        }

        $storedPath = null;
        if ($this->hasFile($uploaded)) {
            if ($uploaded->getError() !== UPLOAD_ERR_OK) {
                $errors['submission_file'] = 'There was a problem uploading the file.';
            } else {
                $mime = $uploaded->getClientMediaType();
                $ext = strtolower(pathinfo($uploaded->getClientFilename(), PATHINFO_EXTENSION));

                if (!isset(self::ALLOWED_FILES[$ext]) || self::ALLOWED_FILES[$ext] !== $mime) {
                    $errors['submission_file'] = 'Only pdf, doc, docx, zip, jpg or png files are allowed.';
                } elseif ($uploaded->getSize() > self::MAX_FILE_SIZE) {
                    $errors['submission_file'] = 'The file size must be at most 10MB.';
                }
            }
        }

        if ($errors !== []) {
            return $this->render($response, 'assignments/show.html.twig', [
                'assignment' => $this->showData($assignment, $latest, $this->state($assignment, $latest)),
                'errors' => array_merge($this->emptyErrors(), $errors),
                'old' => ['submission_text' => $text],
            ]);
        }

        if ($this->hasFile($uploaded)) {
            $ext = strtolower(pathinfo($uploaded->getClientFilename(), PATHINFO_EXTENSION));
            $version = $latest !== null ? $latest->version + 1 : 1;
            $relDir = $assignment->id . '/' . $user->id;
            $absDir = self::UPLOAD_DIR . '/' . $relDir;

            if (!is_dir($absDir)) {
                mkdir($absDir, 0775, true);
            }

            $filename = 'v' . $version . '.' . $ext;
            $uploaded->moveTo($absDir . '/' . $filename);
            $storedPath = 'assignments/' . $relDir . '/' . $filename;
        }

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'version' => $latest !== null ? $latest->version + 1 : 1,
            'submission_text' => $text !== '' ? $text : null,
            'file_path' => $storedPath,
            'status' => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        $this->flash->success('Assignment submitted successfully.');

        return $this->redirect('/assignments/' . $assignment->id);
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

    private function showData(Assignment $assignment, ?AssignmentSubmission $latest, array $state): array
    {
        $history = $assignment->submissions->map(static function (AssignmentSubmission $s): array {
            return [
                'version' => $s->version,
                'submitted_at' => $s->submitted_at?->format('Y-m-d H:i:s'),
                'submission_text' => $s->submission_text,
                'file_path' => $s->file_path,
                'score' => $s->score,
                'feedback' => $s->feedback,
            ];
        })->values()->all();

        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'description' => $assignment->description,
            'course_title' => $assignment->course?->title,
            'due_date' => $assignment->due_date?->format('Y-m-d H:i:s'),
            'max_score' => $assignment->max_score,
            'state' => $state,
            'latest' => $latest !== null ? [
                'version' => $latest->version,
                'submitted_at' => $latest->submitted_at?->format('Y-m-d H:i:s'),
                'submission_text' => $latest->submission_text,
                'file_path' => $latest->file_path,
                'score' => $latest->score,
                'feedback' => $latest->feedback,
            ] : null,
            'history' => $history,
        ];
    }

    private function state(Assignment $assignment, ?AssignmentSubmission $latest): array
    {
        $now = new DateTimeImmutable();
        $due = $assignment->due_date;
        $overdue = $due !== null && $due < $now;

        if ($latest === null) {
            return $overdue
                ? ['key' => 'missed', 'label' => 'Missed', 'can_submit' => false]
                : ['key' => 'open', 'label' => 'Open - submit now', 'can_submit' => true];
        }

        if ($latest->score !== null) {
            return ['key' => 'graded', 'label' => 'Graded', 'can_submit' => false];
        }

        return $overdue
            ? ['key' => 'locked', 'label' => 'Submitted (deadline passed)', 'can_submit' => false]
            : ['key' => 'submitted', 'label' => 'Submitted', 'can_submit' => true];
    }

    private function hasFile(?UploadedFileInterface $file): bool
    {
        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    private function isEnrolled(int $userId, ?int $courseId): bool
    {
        if ($courseId === null) {
            return false;
        }

        return UserCourse::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereIn('status', ['enrolled', 'in_progress', 'completed'])
            ->exists();
    }

    private function render404(Response $response, string $title): Response
    {
        return $this->render($response, 'errors/404_message.html.twig', ['message' => $title])->withStatus(404);
    }

    private function emptyErrors(): array
    {
        return [
            'submission_text' => '',
            'submission_file' => '',
        ];
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