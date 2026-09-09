<?php

declare(strict_types=1);

use Slim\App;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'home.html.twig');
    })->setName('home');

    // Health check (useful for dev, uptime checks)
    $app->get('/ping', function (Request $request, Response $response) {
        $response->getBody()->write('pong');
        return $response;
    })->setName('ping');
};