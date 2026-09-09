<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Middleware\RedirectIfAuthenticatedMiddleware;
use App\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {
    $container = $app->getContainer();

    $app->get('/', [\App\Controllers\DashboardController::class, 'index'])->setName('home');

    // Health check (useful for dev, uptime checks)
    $app->get('/ping', function (Request $request, Response $response) {
        $response->getBody()->write('pong');
        return $response;
    })->setName('ping');

    // Guest-only routes (register + login)
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/register', [AuthController::class, 'showRegister'])->setName('register');
        $app->post('/register', [AuthController::class, 'postRegister']);
        $app->get('/register/details', [AuthController::class, 'showDetails'])->setName('register.details');
        $app->post('/register/details', [AuthController::class, 'postDetails']);
        $app->get('/register/account', [AuthController::class, 'showAccount'])->setName('register.account');
        $app->post('/register/account', [AuthController::class, 'postAccount']);
        $app->get('/login', [AuthController::class, 'showLogin'])->setName('login');
        $app->post('/login', [AuthController::class, 'postLogin']);
    })->add($container->get(RedirectIfAuthenticatedMiddleware::class));

    // Authenticated-only routes
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/profile', [\App\Controllers\ProfileController::class, 'index'])->setName('profile');
        $app->post('/profile', [\App\Controllers\ProfileController::class, 'update']);
        $app->post('/profile/photo', [\App\Controllers\ProfileController::class, 'photo']);
        $app->post('/profile/password', [\App\Controllers\ProfileController::class, 'password']);
        $app->post('/profile/email', [\App\Controllers\ProfileController::class, 'email']);
        $app->get('/profile/email/verify', [\App\Controllers\ProfileController::class, 'verifyEmail']);

        $app->get('/assignments', [\App\Controllers\AssignmentController::class, 'index'])->setName('assignments');
        $app->get('/assignments/{id:[0-9]+}', [\App\Controllers\AssignmentController::class, 'show']);
        $app->post('/assignments/{id:[0-9]+}/submit', [\App\Controllers\AssignmentController::class, 'submit']);

        $app->get('/routine', [\App\Controllers\RoutineController::class, 'index'])->setName('routine');

        $app->get('/attendance', [\App\Controllers\AttendanceController::class, 'index'])->setName('attendance');

        $app->get('/fees', [\App\Controllers\FeeController::class, 'index'])->setName('fees');
        $app->post('/fees/pay', [\App\Controllers\FeeController::class, 'pay']);
    })->add($container->get(RequireAuthMiddleware::class));

    $app->post('/logout', [AuthController::class, 'logout'])
        ->setName('logout')
        ->add($container->get(RequireAuthMiddleware::class));
};