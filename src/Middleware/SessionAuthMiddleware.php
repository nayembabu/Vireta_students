<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final class SessionAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Flash $flash,
        private readonly Twig $twig
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->auth->user();
        $twigEnv = $this->twig->getEnvironment();

        $twigEnv->addGlobal('auth', $user !== null);
        $twigEnv->addGlobal('staff_base', $user !== null
            ? ($user->role?->slug === 'trainer' ? '/trainer' : ($user->role?->slug === 'cashier' ? '/cashier' : '/admin'))
            : '/admin');
        $twigEnv->addGlobal('auth_user', $user !== null
            ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role' => $user->role?->name,
                'role_slug' => $user->role?->slug,
                'status' => $user->status,
                'batch_id' => $user->batch_id,
                'student_id' => $user->student_id,
                'pro_pic' => $user->student?->pro_pic,
            ]
            : null);

        $twigEnv->addGlobal('flash', $this->flash->pull());

        return $handler->handle($request);
    }
}