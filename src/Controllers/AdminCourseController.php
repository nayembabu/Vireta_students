<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Course;
use App\Support\BasePath;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class AdminCourseController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $courses = Course::orderBy('title')->get();

        $items = $courses->map(static function (Course $c): array {
            return [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'fee' => (float)$c->fee,
                'duration_weeks' => $c->duration_weeks,
                'status' => $c->status,
                'batch_count' => $c->batches()->count(),
            ];
        })->values()->all();

        return $this->render($response, 'admin/courses/index.html.twig', ['items' => $items]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/courses/form.html.twig', [
            'course' => null,
            'old' => $this->emptyOld(),
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, null);

        if ($errors !== []) {
            return $this->render($response, 'admin/courses/form.html.twig', [
                'course' => null,
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        Course::create([
            'title' => $old['title'],
            'slug' => $this->uniqueSlug($old['title']),
            'description' => $old['description'] !== '' ? $old['description'] : null,
            'duration_weeks' => $old['duration_weeks'],
            'fee' => $old['fee'],
            'status' => $old['status'],
        ]);

        $this->flash->success('Course created successfully.');

        return $this->redirect('/admin/courses');
    }

    public function editForm(Request $request, Response $response): Response
    {
        $course = $this->find($request);

        if ($course === null) {
            return $this->render404($response, 'Course not found.');
        }

        return $this->render($response, 'admin/courses/form.html.twig', [
            'course' => $course,
            'old' => [
                'title' => $course->title,
                'description' => $course->description ?? '',
                'duration_weeks' => (int)$course->duration_weeks,
                'fee' => (float)$course->fee,
                'status' => $course->status,
            ],
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function edit(Request $request, Response $response): Response
    {
        $course = $this->find($request);

        if ($course === null) {
            return $this->render404($response, 'Course not found.');
        }

        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, $course->id);

        if ($errors !== []) {
            return $this->render($response, 'admin/courses/form.html.twig', [
                'course' => $course,
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        $course->update([
            'title' => $old['title'],
            'slug' => $this->uniqueSlug($old['title'], $course->id),
            'description' => $old['description'] !== '' ? $old['description'] : null,
            'duration_weeks' => $old['duration_weeks'],
            'fee' => $old['fee'],
            'status' => $old['status'],
        ]);

        $this->flash->success('Course updated successfully.');

        return $this->redirect('/admin/courses');
    }

    public function delete(Request $request, Response $response): Response
    {
        $course = $this->find($request);

        if ($course === null) {
            return $this->render404($response, 'Course not found.');
        }

        if ($course->batches()->count() > 0) {
            $this->flash->error('This course is linked to batches and cannot be deleted.');
            return $this->redirect('/admin/courses');
        }

        $course->delete();
        $this->flash->success('Course deleted.');

        return $this->redirect('/admin/courses');
    }

    private function find(Request $request): ?Course
    {
        return Course::find($this->routeId($request));
    }

    private function normalize(array $data): array
    {
        return [
            'title' => trim((string)($data['title'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'duration_weeks' => max(0, (int)($data['duration_weeks'] ?? 0)),
            'fee' => (float)($data['fee'] ?? 0),
            'status' => trim((string)($data['status'] ?? 'draft')),
        ];
    }

    private function validate(array $old, ?int $ignoreId): array
    {
        $errors = [];

        if ($old['title'] === '') {
            $errors['title'] = 'Course title is required.';
        }
        if ($old['fee'] < 0) {
            $errors['fee'] = 'Fee cannot be negative.';
        }
        if (!in_array($old['status'], ['draft', 'published', 'archived'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        return $errors;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = strtolower(trim($title));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? 'course';
        $base = trim($base, '-') ?: 'course';

        $slug = $base;
        $i = 1;
        $query = Course::where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        while ($query->exists()) {
            $slug = $base . '-' . $i;
            $i++;
            $query = Course::where('slug', $slug);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
        }

        return $slug;
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

    private function emptyErrors(): array
    {
        return [
            'title' => '',
            'description' => '',
            'duration_weeks' => '',
            'fee' => '',
            'status' => '',
        ];
    }

    private function emptyOld(): array
    {
        return [
            'title' => '',
            'description' => '',
            'duration_weeks' => 0,
            'fee' => 0,
            'status' => 'draft',
        ];
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
