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

final class AdminCashierController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        // batch is a nullable belongsTo relation; resolved lazily.
        $cashiers = User::whereHas('role', fn ($q) => $q->where('slug', 'cashier'))
            ->orderBy('name')
            ->get();

        $items = $cashiers->map(static function (User $u): array {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'username' => $u->username,
                'phone' => $u->phone,
                'status' => $u->status,
            ];
        })->values()->all();

        return $this->render($response, 'admin/cashiers/index.html.twig', ['items' => $items]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/cashiers/form.html.twig', [
            'cashier' => null,
            'old' => $this->emptyOld(),
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, null);

        if ($errors !== []) {
            return $this->render($response, 'admin/cashiers/form.html.twig', [
                'cashier' => null,
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
            'role_id' => Role::CASHIER,
            'status' => $old['status'],
        ]);

        $this->flash->success('Cashier account created successfully.');

        return $this->redirect('/admin/cashiers');
    }

    public function editForm(Request $request, Response $response): Response
    {
        $cashier = $this->find($request);

        if ($cashier === null) {
            return $this->render404($response, 'Cashier not found.');
        }

        return $this->render($response, 'admin/cashiers/form.html.twig', [
            'cashier' => $cashier,
            'old' => [
                'name' => $cashier->name,
                'email' => $cashier->email,
                'phone' => $cashier->phone ?? '',
                'status' => $cashier->status,
            ],
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function edit(Request $request, Response $response): Response
    {
        $cashier = $this->find($request);

        if ($cashier === null) {
            return $this->render404($response, 'Cashier not found.');
        }

        $old = $this->normalize($request->getParsedBody());
        $errors = $this->validate($old, $cashier->id);

        if ($errors !== []) {
            return $this->render($response, 'admin/cashiers/form.html.twig', [
                'cashier' => $cashier,
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        $data = [
            'name' => $old['name'],
            'email' => $old['email'],
            'phone' => $old['phone'] !== '' ? $old['phone'] : null,
            'status' => $old['status'],
        ];

        if ($old['password'] !== '') {
            $data['password_hash'] = password_hash($old['password'], PASSWORD_DEFAULT);
        }

        $cashier->update($data);

        $this->flash->success('Cashier updated successfully.');

        return $this->redirect('/admin/cashiers');
    }

    public function delete(Request $request, Response $response): Response
    {
        $cashier = $this->find($request);

        if ($cashier === null) {
            return $this->render404($response, 'Cashier not found.');
        }

        $cashier->delete();
        $this->flash->success('Cashier account deleted.');

        return $this->redirect('/admin/cashiers');
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
            'status' => trim((string)($data['status'] ?? 'active')),
        ];
    }

    private function validate(array $old, ?int $ignoreId): array
    {
        $errors = [];

        if ($old['name'] === '') {
            $errors['name'] = 'Cashier name is required.';
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
