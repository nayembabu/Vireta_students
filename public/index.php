<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Build PHP-DI container from definitions
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/definitions.php');

if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
    $containerBuilder->enableCompilation(__DIR__ . '/../cache/di');
    $containerBuilder->writeProxiesToFile(true, __DIR__ . '/../cache/di/proxies');
}

$container = $containerBuilder->build();

// Boot Eloquent (Illuminate Database) so static model queries work
$container->get('db');

// Create app
$app = Bridge::create($container);

// Set base path so the app works under nested URLs (e.g. localhost/php/slim4/vireta_students)
$app->setBasePath(\App\Support\BasePath::detect($_SERVER));

// Register routes
(require __DIR__ . '/../config/routes.php')($app);
(require __DIR__ . '/../config/middleware.php')($app);

// Run app
$app->run();