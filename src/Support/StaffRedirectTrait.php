<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Redirects to a staff section path using the correct base prefix.
 * The same controller is mounted under both /admin (admin only) and
 * /trainer (trainer only), so the redirect target must follow the
 * prefix of the current request.
 */
trait StaffRedirectTrait
{
    protected function staffRedirect(Request $request, string $path): Response
    {
        $base = BasePath::detect($_SERVER);
        $uri = $request->getUri()->getPath();
        $prefix = str_starts_with($uri, '/trainer') ? '/trainer'
            : (str_starts_with($uri, '/cashier') ? '/cashier' : '/admin');

        $response = (new \Slim\Psr7\Response())->withStatus(302);
        return $response->withHeader('Location', $base . $prefix . $path);
    }
}
