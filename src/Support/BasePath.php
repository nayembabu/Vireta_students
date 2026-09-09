<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Detects the application base path so the app works both from a
 * project-root web server and from a nested Apache alias
 * (e.g. http://localhost/php/slim4/vireta_students/).
 */
final class BasePath
{
    public static function detect(array $server): string
    {
        $scriptName = str_replace('\\', '/', (string)($server['SCRIPT_NAME'] ?? '/index.php'));

        // Served through public/index.php (recommended layout)
        if (str_ends_with($scriptName, '/public/index.php')) {
            return rtrim(substr($scriptName, 0, -strlen('/public/index.php')), '/');
        }

        // Front controller at the webroot
        if (str_ends_with($scriptName, '/index.php')) {
            return rtrim(substr($scriptName, 0, -strlen('/index.php')), '/');
        }

        return '';
    }
}