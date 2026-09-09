<?php

declare(strict_types=1);

use App\Middleware\CsrfMiddleware;
use App\Middleware\SessionAuthMiddleware;
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

    // Auth globals (auth_user, flash) for templates - runs after session started
    $app->add($container->get(SessionAuthMiddleware::class));

    // CSRF protection - runs after session started
    $app->add($container->get(CsrfMiddleware::class));

    // Session middleware (start session for the request)
    $app->add(function ($request, $handler) use ($container) {
        /** @var \Odan\Session\SessionManagerInterface $session */
        $session = $container->get(\Odan\Session\SessionManagerInterface::class);
        $session->start();

        $result = $handler->handle($request);

        $session->save();
        return $result;
    });

    // Slim routing middleware (added first so the error middleware can wrap it)
    $app->addRoutingMiddleware();

    // Slim error middleware (added last -> outermost -> catches errors from
    // everything below, including routing exceptions like 404)
    $settings = $container->get('settings');
    $app->addErrorMiddleware(
        (bool)$settings['environment']['app_debug'],
        true,
        true
    );
};