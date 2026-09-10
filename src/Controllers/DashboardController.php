<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Services\DashboardService;
use App\Support\BasePath;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class DashboardController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly DashboardService $dashboard
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->twig->render($response, 'home.html.twig');
        }

        $role = $user->role?->slug;

        if ($role === 'trainer') {
            return $this->redirect('/trainer');
        }

        if ($role === 'cashier') {
            return $this->redirect('/cashier');
        }

        if ($role === 'admin') {
            return $this->redirect('/admin');
        }

        return $this->twig->render($response, 'dashboard/index.html.twig', [
            'data' => $this->dashboard->build($user),
        ]);
    }

    private function redirect(string $url): Response
    {
        $response = (new \Slim\Psr7\Response())->withStatus(302);
        return $response->withHeader('Location', BasePath::detect($_SERVER) . $url);
    }
}