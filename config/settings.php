<?php

declare(strict_types=1);

use Monolog\Logger;

return [
    'environment' => [
        'app_name' => $_ENV['APP_NAME'] ?? 'ViretaDev Student Portal',
        'app_version' => '0.1.0',
        'app_url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
        'app_env' => $_ENV['APP_ENV'] ?? 'development',
        'app_debug' => (bool)($_ENV['APP_DEBUG'] ?? true),
        'app_key' => $_ENV['APP_KEY'] ?? '',
    ],

    'app' => [
        'base_path' => '',
    ],

    'twig' => [
        'templates' => __DIR__ . '/../templates',
        'cache' => __DIR__ . '/../cache/twig',
    ],

    'db' => [
        'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_NAME'] ?? 'vireta_students',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    ],

    'logger' => [
        'name' => 'vireta-students',
        'path' => __DIR__ . '/../logs/app.log',
        'level' => Logger::DEBUG,
    ],

    'session' => [
        'name' => $_ENV['SESSION_NAME'] ?? 'vireta_session',
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'mailer' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['MAIL_PORT'] ?? 25),
        'username' => $_ENV['MAIL_USER'] ?? '',
        'password' => $_ENV['MAIL_PASS'] ?? '',
    ],
];