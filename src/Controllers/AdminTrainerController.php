<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Batch;
use App\Models\Role;
use App\Models\User;
use App\Support\BasePath;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class AdminTrainerController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        // batch is a nullable belongsTo relation; resolved lazily to avoid the
        // PHP 8.5 "null array offset" deprecation in Eloquent.
        $trainers = User::whereHas('role', fn ($q) => $q->where('slug', 'trainer'))
            ->orderBy('name')
            ->get();

        $items = $trainers->map(static function (User $u): array {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'username' => $u->username,
                'phone' => $u->phone,
                'batch' => $u->batch?->name,
                'status' => $u->status,
            ];
        })->values()->all();

        return $this->render($response, 'admin/trainers/index.html.twig', [
            'items' => $items,
            'batches' => Batch::orderBy('name')->get(),
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/trainers/form.html.twig', [
            'trainer' => null,
            'batches' => Batch::orderBy('name')->get(),
            'old' => $this->emptyOld(),
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, null);

        if ($errors !== []) {
            return $this->render($response, 'admin/trainers/form.html.twig', [
                'trainer' => null,
                'batches' => Batch::orderBy('name')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        User::create([
            'name' => $old['name'],
            'username' => $this->uniqueUsername($old['email']),
            'email' => $old['email'],
            'phone' => $old['phone'] !== '' ? $old['phone'] : null,
            'password_hash' => password_hash($old['password'], PASSWORD_DEFAULT),
            'role_id' => Role::TRAINER,
            'status' => $old['status'],
            'batch_id' => $old['batch_id'] > 0 ? $old['batch_id'] : null,
        ]);

        $this->flash->success('Trainer account created successfully.');

        return $this->redirect('/admin/trainers');
    }

    public function editForm(Request $request, Response $response): Response
    {
        $trainer = $this->find($request);

        if ($trainer === null) {
            return $this->render404($response, 'Trainer not found.');
        }

        return $this->render($response, 'admin/trainers/form.html.twig', [
            'trainer' => $trainer,
            'batches' => Batch::orderBy('name')->get(),
            'old' => [
                'name' => $trainer->name,
                'email' => $trainer->email,
                'phone' => $trainer->phone ?? '',
                'batch_id' => (int)$trainer->batch_id,
                'status' => $trainer->status,
            ],
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function edit(Request $request, Response $response): Response
    {
        $trainer = $this->find($request);

        if ($trainer === null) {
            return $this->render404($response, 'Trainer not found.');
        }

        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, $trainer->id);

        if ($errors !== []) {
            return $this->render($response, 'admin/trainers/form.html.twig', [
                'trainer' => $trainer,
                'batches' => Batch::orderBy('name')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        $data = [
            'name' => $old['name'],
            'email' => $old['email'],
            'phone' => $old['phone'] !== '' ? $old['phone'] : null,
            'status' => $old['status'],
            'batch_id' => $old['batch_id'] > 0 ? $old['batch_id'] : null,
        ];

        if ($old['password'] !== '') {
            $data['password_hash'] = password_hash($old['password'], PASSWORD_DEFAULT);
        }

        $trainer->update($data);

        $this->flash->success('Trainer updated successfully.');

        return $this->redirect('/admin/trainers');
    }

    public function delete(Request $request, Response $response): Response
    {
        $trainer = $this->find($request);

        if ($trainer === null) {
            return $this->render404($response, 'Trainer not found.');
        }

        $trainer->delete();
        $this->flash->success('Trainer account deleted.');

        return $this->redirect('/admin/trainers');
    }

    private function find(Request $request): ?User
    {
        return User::find($this->routeId($request));
    }

    private function normalize(array $data): array
    {
        return [
            'name' => trim((string)($data['name'] ?? '')),
            'email' => strtolower(trim((string)($data['email'] ?? ''))),
            'phone' => trim((string)($data['phone'] ?? '')),
            'password' => (string)($data['password'] ?? ''),
            'batch_id' => (int)($data['batch_id'] ?? 0),
            'status' => trim((string)($data['status'] ?? 'active')),
        ];
    }

    private function validate(array $old, ?int $ignoreId): array
    {
        $errors = [];

        if ($old['name'] === '') {
            $errors['name'] = 'Trainer name is required.';
        }
        if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email.';
        } elseif (User::where('email', $old['email'])
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $errors['email'] = 'An account already exists with this email.';
        }
        if ($ignoreId === null && strlen($old['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif ($ignoreId !== null && $old['password'] !== '' && strlen($old['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if (!in_array($old['status'], ['active', 'inactive', 'suspended'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        return $errors;
    }

    private function uniqueUsername(string $email): string
    {
        $base = strtolower(trim(explode('@', $email)[0] ?? ''));
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'user';

        $username = $base;
        $i = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . $i;
            $i++;
        }

        return $username;
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
            'email' => '',
            'phone' => '',
            'password' => '',
            'batch_id' => '',
            'status' => '',
        ];
    }

    private function emptyOld(): array
    {
        return [
            'name' => '',
            'email' => '',
            'phone' => '',
            'password' => '',
            'batch_id' => '',
            'status' => 'active',
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
