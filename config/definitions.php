<?php

declare(strict_types=1);

use Slim\App;
use Slim\Views\Twig;
use Twig\Extension\DebugExtension;
use Twig\TwigFunction;

return [
    App::class => function ($container) {
        // NOTE: The App is created in public/index.php via Bridge::create()
        // This definition is a fallback for tests / CLI usage.

        $settings = $container->get('settings');
        $app = \Slim\Factory\AppFactory::createFromContainer($container);
        $app->addRoutingMiddleware();
        return $app;
    },

    'settings' => function () {
        $settings = require __DIR__ . '/settings.php';
        return $settings;
    },

    \Slim\Views\Twig::class => function ($container) {
        $settings = $container->get('settings');
        $environment = $settings['environment'];

        $twig = Twig::create(
            $settings['twig']['templates'],
            [
                'cache' => $environment['app_debug'] ? false : $settings['twig']['cache'],
                'debug' => $environment['app_debug'],
                'auto_reload' => $environment['app_debug'],
                'strict_variables' => $environment['app_debug'],
            ]
        );

        if ($environment['app_debug']) {
            $twig->addExtension(new DebugExtension());
        }

        // Global template vars
        $twig->getEnvironment()->addGlobal('app_name', $environment['app_name']);
        $twig->getEnvironment()->addGlobal('app_version', $environment['app_version']);
        $twig->getEnvironment()->addGlobal('base_path', \App\Support\BasePath::detect($_SERVER));

        $twig->getEnvironment()->addFunction(new TwigFunction('asset', function (string $asset): string {
            $basePath = \App\Support\BasePath::detect($_SERVER);
            return $basePath . '/assets/' . ltrim($asset, '/');
        }));

        $twig->getEnvironment()->addFunction(new TwigFunction('upload', function (string $path): string {
            $basePath = \App\Support\BasePath::detect($_SERVER);
            return $basePath . '/uploads/' . ltrim($path, '/');
        }));

        return $twig;
    },

    // Illuminate Database (Capsule)
    'db' => function ($container) {
        $settings = $container->get('settings')['db'];
        $capsule = new \Illuminate\Database\Capsule\Manager();

        $capsule->addConnection([
            'driver' => $settings['driver'],
            'host' => $settings['host'],
            'port' => $settings['port'],
            'database' => $settings['database'],
            'username' => $settings['username'],
            'password' => $settings['password'],
            'charset' => $settings['charset'],
            'collation' => $settings['collation'],
            'prefix' => '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    },

    // PDO
    \PDO::class => function ($container) {
        $settings = $container->get('settings')['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $settings['host'],
            $settings['port'],
            $settings['database'],
            $settings['charset']
        );
        $pdo = new \PDO($dsn, $settings['username'], $settings['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    },

    \Psr\Log\LoggerInterface::class => function ($container) {
        $settings = $container->get('settings')['logger'];
        $logger = new \Monolog\Logger($settings['name']);
        $logger->pushHandler(
            new \Monolog\Handler\StreamHandler($settings['path'], $settings['level'])
        );
        return $logger;
    },

    // Session
    \Odan\Session\PhpSession::class => function ($container) {
        $settings = $container->get('settings')['session'];

        // odan/session v6 options; unknown keys are passed to ini_set('session.' . $key)
        return new \Odan\Session\PhpSession([
            'name' => (string)($settings['name'] ?? 'vireta_session'),
            'lifetime' => (int)($settings['lifetime'] ?? 7200),
            'path' => (string)($settings['path'] ?? '/'),
            'domain' => null,
            'secure' => (bool)($settings['secure'] ?? false),
            'httponly' => (bool)($settings['httponly'] ?? true),
            'cache_limiter' => 'nocache',
            'cookie_samesite' => (string)($settings['samesite'] ?? 'Lax'),
        ]);
    },

    \Odan\Session\SessionManagerInterface::class => function ($container) {
        return $container->get(\Odan\Session\PhpSession::class);
    },

    \Odan\Session\SessionInterface::class => function ($container) {
        return $container->get(\Odan\Session\PhpSession::class);
    },

    // Symfony Mailer
    \Symfony\Component\Mailer\MailerInterface::class => function ($container) {
        $settings = $container->get('settings')['mailer'];
        $dsn = sprintf(
            'smtp://%s:%s@%s:%s',
            $settings['username'],
            $settings['password'],
            $settings['host'],
            $settings['port']
        );
        $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
        return new \Symfony\Component\Mailer\Mailer($transport);
    },

    // Auth
    \App\Auth\AuthService::class => function ($container) {
        return new \App\Auth\AuthService($container->get(\Odan\Session\SessionManagerInterface::class));
    },

    // Flash messages
    \App\Support\Flash::class => function ($container) {
        return new \App\Support\Flash($container->get(\Odan\Session\SessionInterface::class));
    },
];