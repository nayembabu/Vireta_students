<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Restricts a route group to logged-in users whose role slug is in the
 * allowed list (e.g. admin, trainer, cashier). Non-staff users are
 * redirected to their dashboard.
 */
final class RequireRoleMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowed;

    public function __construct(
        private readonly AuthService $auth,
        array $allowed
    ) {
        $this->allowed = $allowed;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->user();

        $base = \App\Support\BasePath::detect($_SERVER);

        if ($user === null) {
            return (new Response())->withHeader('Location', $base . '/login')->withStatus(302);
        }

        $slug = $user->role?->slug;

        if (!in_array($slug, $this->allowed, true)) {
            return (new Response())->withHeader('Location', $base . '/')->withStatus(302);
        }

        return $handler->handle($request);
    }
}
