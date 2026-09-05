<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'CaddyPanel\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $path = APP_ROOT . '/src/php/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
