<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->auth->check()) {
            $base = \App\Support\BasePath::detect($_SERVER);
            $response = (new Response())->withHeader('Location', $base . '/login')->withStatus(302);
            return $response;
        }

        return $handler->handle($request);
    }
}