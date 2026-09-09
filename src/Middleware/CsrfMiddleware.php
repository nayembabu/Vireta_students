<?php

declare(strict_types=1);

namespace App\Middleware;

use Odan\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Views\Twig;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly Twig $twig
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->session->get('csrf_token');

        if ($token === null || $token === '') {
            $token = bin2hex(random_bytes(16));
            $this->session->set('csrf_token', $token);
        }

        $method = strtoupper($request->getMethod());

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $body = $request->getParsedBody();

            if (!is_array($body) || !isset($body['_csrf']) || !hash_equals($token, (string)$body['_csrf'])) {
                throw new HttpForbiddenException($request, 'CSRF token mismatch');
            }
        }

        $this->twig->getEnvironment()->addGlobal('csrf_token', $token);

        return $handler->handle($request);
    }
}