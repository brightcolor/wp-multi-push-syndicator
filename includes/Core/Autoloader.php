<?php

namespace WMPS\Core;

if (! defined('ABSPATH')) {
    exit;
}

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        $prefix = 'WMPS\\';

        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $relative = str_replace('\\', '/', $relative);
        $path = WMPS_PLUGIN_DIR . '/includes/' . $relative . '.php';

        if (file_exists($path)) {
            require_once $path;
        }
    }
}