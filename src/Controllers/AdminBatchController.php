<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Batch;
use App\Models\Course;
use App\Support\BasePath;
use App\Support\Flash;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class AdminBatchController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        // course is a nullable belongsTo relation; resolved lazily to avoid the
        // PHP 8.5 "null array offset" deprecation in Eloquent.
        $batches = Batch::orderBy('name')->get();

        $items = $batches->map(static function (Batch $b): array {
            return [
                'id' => $b->id,
                'name' => $b->name,
                'course' => $b->course?->title,
                'start_date' => $b->start_date instanceof \DateTimeInterface ? $b->start_date->format('Y-m-d') : null,
                'end_date' => $b->end_date instanceof \DateTimeInterface ? $b->end_date->format('Y-m-d') : null,
                'status' => $b->status,
                'student_count' => $b->students()->count(),
            ];
        })->values()->all();

        return $this->render($response, 'admin/batches/index.html.twig', [
            'items' => $items,
            'courses' => Course::orderBy('title')->get(),
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/batches/form.html.twig', [
            'batch' => null,
            'courses' => Course::orderBy('title')->get(),
            'old' => $this->emptyOld(),
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, null);

        if ($errors !== []) {
            return $this->render($response, 'admin/batches/form.html.twig', [
                'batch' => null,
                'courses' => Course::orderBy('title')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        Batch::create([
            'name' => $old['name'],
            'course_id' => $old['course_id'] > 0 ? $old['course_id'] : null,
            'start_date' => $old['start_date'] !== '' ? $old['start_date'] : null,
            'end_date' => $old['end_date'] !== '' ? $old['end_date'] : null,
            'status' => $old['status'],
        ]);

        $this->flash->success('Batch created successfully.');

        return $this->redirect('/admin/batches');
    }

    public function editForm(Request $request, Response $response): Response
    {
        $batch = $this->find($request);

        if ($batch === null) {
            return $this->render404($response, 'Batch not found.');
        }

        return $this->render($response, 'admin/batches/form.html.twig', [
            'batch' => $batch,
            'courses' => Course::orderBy('title')->get(),
            'old' => [
                'name' => $batch->name,
                'course_id' => (int)$batch->course_id,
                'start_date' => $batch->start_date instanceof \DateTimeInterface ? $batch->start_date->format('Y-m-d') : '',
                'end_date' => $batch->end_date instanceof \DateTimeInterface ? $batch->end_date->format('Y-m-d') : '',
                'status' => $batch->status,
            ],
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function edit(Request $request, Response $response): Response
    {
        $batch = $this->find($request);

        if ($batch === null) {
            return $this->render404($response, 'Batch not found.');
        }

        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, $batch->id);

        if ($errors !== []) {
            return $this->render($response, 'admin/batches/form.html.twig', [
                'batch' => $batch,
                'courses' => Course::orderBy('title')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        $batch->update([
            'name' => $old['name'],
            'course_id' => $old['course_id'] > 0 ? $old['course_id'] : null,
            'start_date' => $old['start_date'] !== '' ? $old['start_date'] : null,
            'end_date' => $old['end_date'] !== '' ? $old['end_date'] : null,
            'status' => $old['status'],
        ]);

        $this->flash->success('Batch updated successfully.');

        return $this->redirect('/admin/batches');
    }

    public function delete(Request $request, Response $response): Response
    {
        $batch = $this->find($request);

        if ($batch === null) {
            return $this->render404($response, 'Batch not found.');
        }

        if ($batch->students()->count() > 0 || $batch->users()->count() > 0) {
            $this->flash->error('This batch has students and cannot be deleted.');
            return $this->redirect('/admin/batches');
        }

        $batch->delete();
        $this->flash->success('Batch deleted.');

        return $this->redirect('/admin/batches');
    }

    private function find(Request $request): ?Batch
    {
        return Batch::find($this->routeId($request));
    }

    private function normalize(array $data): array
    {
        return [
            'name' => trim((string)($data['name'] ?? '')),
            'course_id' => (int)($data['course_id'] ?? 0),
            'start_date' => trim((string)($data['start_date'] ?? '')),
            'end_date' => trim((string)($data['end_date'] ?? '')),
            'status' => trim((string)($data['status'] ?? 'upcoming')),
        ];
    }

    private function validate(array $old, ?int $ignoreId): array
    {
        $errors = [];

        if ($old['name'] === '') {
            $errors['name'] = 'Batch name is required.';
        } elseif (Batch::where('name', $old['name'])
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $errors['name'] = 'A batch with this name already exists.';
        }

        if ($old['start_date'] !== '' && !DateTimeImmutable::createFromFormat('Y-m-d', $old['start_date'])) {
            $errors['start_date'] = 'Please enter a valid start date.';
        }
        if ($old['end_date'] !== '' && !DateTimeImmutable::createFromFormat('Y-m-d', $old['end_date'])) {
            $errors['end_date'] = 'Please enter a valid end date.';
        }

        if (!in_array($old['status'], ['upcoming', 'active', 'completed', 'archived'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        return $errors;
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
            'name' => '',
            'course_id' => '',
            'start_date' => '',
            'end_date' => '',
            'status' => '',
        ];
    }

    private function emptyOld(): array
    {
        return [
            'name' => '',
            'course_id' => '',
            'start_date' => '',
            'end_date' => '',
            'status' => 'upcoming',
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
