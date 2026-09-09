<?php

declare(strict_types=1);

use Slim\App;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    $app->addBodyParsingMiddleware();
    $app->add(new MethodOverrideMiddleware());

    // Twig rendering middleware
    $twig = $container->get(Twig::class);
    $app->add(TwigMiddleware::create($app, $twig));

    // Session middleware (start session for the request)
    $app->add(function ($request, $handler) use ($container) {
        /** @var \Odan\Session\SessionInterface $session */
        $session = $container->get(\Odan\Session\SessionInterface::class);
        $session->start();

        $result = $handler->handle($request);

        $session->save();
        return $result;
    });

    // Slim error middleware (add last -> catches errors from everything below)
    $settings = $container->get('settings');
    $errorMiddleware = $app->addErrorMiddleware(
        (bool)$settings['environment']['app_debug'],
        true,
        true
    );

    $app->addRoutingMiddleware();
};