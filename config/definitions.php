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

        $twig->getEnvironment()->addFunction(new TwigFunction('asset', function (string $asset): string {
            $basePath = \App\Support\BasePath::detect($_SERVER);
            return $basePath . '/assets/' . ltrim($asset, '/');
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
    \Odan\Session\SessionInterface::class => function ($container) {
        $settings = $container->get('settings')['session'];

        $sessionId = (string)($settings['name'] ?? 'vireta_session');

        // Apply PHP session options
        $lifetime = (int)($settings['lifetime'] ?? 7200);
        $path = (string)($settings['path'] ?? '/');
        $secure = (bool)($settings['secure'] ?? false);
        $httponly = (bool)($settings['httponly'] ?? true);
        $samesite = (string)($settings['samesite'] ?? 'Lax');

        ini_set('session.name', $sessionId);
        ini_set('session.gc_maxlifetime', (string)$lifetime);
        ini_set('session.cookie_lifetime', (string)$lifetime);
        ini_set('session.cookie_path', $path);
        ini_set('session.cookie_httponly', $httponly ? '1' : '0');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);

        return new \Odan\Session\PhpSession();
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
        $transport = \Symfony\Component\Mailer\Transport\Transport::fromDsn($dsn);
        return new \Symfony\Component\Mailer\Mailer($transport);
    },
];