<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Routine;
use App\Models\User;
use App\Support\BasePath;
use App\Support\Flash;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class AdminRoutineController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $batchId = (int)($request->getQueryParams()['batch'] ?? 0);

        $query = Routine::with(['batch', 'course', 'mentor']);

        if ($batchId > 0) {
            $query->where('batch_id', $batchId);
        }

        $routines = $query->orderByDesc('session_date')->orderBy('start_time')->get();

        $items = $routines->map(static function (Routine $r): array {
            return [
                'id' => $r->id,
                'session_date' => $r->session_date?->format('Y-m-d'),
                'start_time' => $r->start_time,
                'end_time' => $r->end_time,
                'topic' => $r->topic,
                'room' => $r->room,
                'batch' => $r->batch?->name,
                'course' => $r->course?->title,
                'mentor' => $r->mentor?->name,
            ];
        })->values()->all();

        return $this->render($response, 'admin/routines/index.html.twig', [
            'items' => $items,
            'batches' => Batch::orderBy('name')->get(),
            'batch_id' => $batchId,
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/routines/form.html.twig', [
            'routine' => null,
            'batches' => Batch::orderBy('name')->get(),
            'courses' => Course::orderBy('title')->get(),
            'mentors' => $this->mentors(),
            'old' => $this->emptyOld(),
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old);

        if ($errors !== []) {
            return $this->render($response, 'admin/routines/form.html.twig', [
                'routine' => null,
                'batches' => Batch::orderBy('name')->get(),
                'courses' => Course::orderBy('title')->get(),
                'mentors' => $this->mentors(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        Routine::create([
            'batch_id' => $old['batch_id'],
            'course_id' => $old['course_id'],
            'mentor_id' => $old['mentor_id'] > 0 ? $old['mentor_id'] : null,
            'session_date' => $old['session_date'],
            'start_time' => $old['start_time'],
            'end_time' => $old['end_time'],
            'topic' => $old['topic'] !== '' ? $old['topic'] : null,
            'room' => $old['room'] !== '' ? $old['room'] : null,
            'meeting_link' => $old['meeting_link'] !== '' ? $old['meeting_link'] : null,
            'created_by' => $this->auth->user()->id,
        ]);

        $this->flash->success('Class added to the routine.');

        return $this->redirect('/admin/routines');
    }

    public function editForm(Request $request, Response $response): Response
    {
        $routine = $this->find($request);

        if ($routine === null) {
            return $this->render404($response, 'Routine entry not found.');
        }

        return $this->render($response, 'admin/routines/form.html.twig', [
            'routine' => $routine,
            'batches' => Batch::orderBy('name')->get(),
            'courses' => Course::orderBy('title')->get(),
            'mentors' => $this->mentors(),
            'old' => [
                'batch_id' => (int)$routine->batch_id,
                'course_id' => (int)$routine->course_id,
                'mentor_id' => (int)$routine->mentor_id,
                'session_date' => $routine->session_date?->format('Y-m-d'),
                'start_time' => $routine->start_time,
                'end_time' => $routine->end_time,
                'topic' => $routine->topic ?? '',
                'room' => $routine->room ?? '',
                'meeting_link' => $routine->meeting_link ?? '',
            ],
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function edit(Request $request, Response $response): Response
    {
        $routine = $this->find($request);

        if ($routine === null) {
            return $this->render404($response, 'Routine entry not found.');
        }

        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, $routine->id);

        if ($errors !== []) {
            return $this->render($response, 'admin/routines/form.html.twig', [
                'routine' => $routine,
                'batches' => Batch::orderBy('name')->get(),
                'courses' => Course::orderBy('title')->get(),
                'mentors' => $this->mentors(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        $routine->update([
            'batch_id' => $old['batch_id'],
            'course_id' => $old['course_id'],
            'mentor_id' => $old['mentor_id'] > 0 ? $old['mentor_id'] : null,
            'session_date' => $old['session_date'],
            'start_time' => $old['start_time'],
            'end_time' => $old['end_time'],
            'topic' => $old['topic'] !== '' ? $old['topic'] : null,
            'room' => $old['room'] !== '' ? $old['room'] : null,
            'meeting_link' => $old['meeting_link'] !== '' ? $old['meeting_link'] : null,
        ]);

        $this->flash->success('Routine entry updated.');

        return $this->redirect('/admin/routines');
    }

    public function delete(Request $request, Response $response): Response
    {
        $routine = $this->find($request);

        if ($routine === null) {
            return $this->render404($response, 'Routine entry not found.');
        }

        $routine->delete();
        $this->flash->success('Routine entry deleted.');

        return $this->redirect('/admin/routines');
    }

    private function find(Request $request): ?Routine
    {
        return Routine::find($this->routeId($request));
    }

    private function mentors(): array
    {
        return User::whereHas('role', fn ($q) => $q->whereIn('slug', ['trainer', 'admin']))
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function normalize(array $data): array
    {
        return [
            'batch_id' => (int)($data['batch_id'] ?? 0),
            'course_id' => (int)($data['course_id'] ?? 0),
            'mentor_id' => (int)($data['mentor_id'] ?? 0),
            'session_date' => trim((string)($data['session_date'] ?? '')),
            'start_time' => trim((string)($data['start_time'] ?? '')),
            'end_time' => trim((string)($data['end_time'] ?? '')),
            'topic' => trim((string)($data['topic'] ?? '')),
            'room' => trim((string)($data['room'] ?? '')),
            'meeting_link' => trim((string)($data['meeting_link'] ?? '')),
        ];
    }

    private function validate(array $old, ?int $ignoreId = null): array
    {
        $errors = [];

        if ($old['batch_id'] <= 0) {
            $errors['batch_id'] = 'Please select a batch.';
        }
        if ($old['course_id'] <= 0) {
            $errors['course_id'] = 'Please select a course.';
        }
        if ($old['session_date'] === '' || !DateTimeImmutable::createFromFormat('Y-m-d', $old['session_date'])) {
            $errors['session_date'] = 'Please enter a valid date (YYYY-MM-DD).';
        }
        if ($old['start_time'] === '' || !preg_match('/^\d{2}:\d{2}/', $old['start_time'])) {
            $errors['start_time'] = 'Please enter a valid start time.';
        }
        if ($old['end_time'] === '' || !preg_match('/^\d{2}:\d{2}/', $old['end_time'])) {
            $errors['end_time'] = 'Please enter a valid end time.';
        } elseif ($old['start_time'] !== '' && $old['end_time'] <= $old['start_time']) {
            $errors['end_time'] = 'End time must be after start time.';
        }

        if ($old['meeting_link'] !== '' && !filter_var($old['meeting_link'], FILTER_VALIDATE_URL)) {
            $errors['meeting_link'] = 'Please enter a valid URL.';
        }

        // Overlap check: same batch + same date, overlapping time range.
        if ($old['batch_id'] > 0 && $old['session_date'] !== '' && $old['start_time'] !== '' && $old['end_time'] !== '') {
            $overlap = Routine::where('batch_id', $old['batch_id'])
                ->where('session_date', $old['session_date'])
                ->where(function ($q) use ($old) {
                    $q->whereBetween('start_time', [$old['start_time'], $old['end_time']])
                        ->orWhereBetween('end_time', [$old['start_time'], $old['end_time']])
                        ->orWhere(function ($q2) use ($old) {
                            $q2->where('start_time', '<=', $old['start_time'])
                                ->where('end_time', '>=', $old['end_time']);
                        });
                });

            if ($ignoreId !== null) {
                $overlap->where('id', '!=', $ignoreId);
            }

            if ($overlap->exists()) {
                $errors['start_time'] = 'This time overlaps with an existing class for the same batch on that date.';
            }
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
            'batch_id' => '',
            'course_id' => '',
            'mentor_id' => '',
            'session_date' => '',
            'start_time' => '',
            'end_time' => '',
            'topic' => '',
            'room' => '',
            'meeting_link' => '',
        ];
    }

    private function emptyOld(): array
    {
        return [
            'batch_id' => '',
            'course_id' => '',
            'mentor_id' => '',
            'session_date' => '',
            'start_time' => '',
            'end_time' => '',
            'topic' => '',
            'room' => '',
            'meeting_link' => '',
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
