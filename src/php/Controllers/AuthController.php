<?php
declare(strict_types=1);

namespace CaddyPanel\Controllers;

use CaddyPanel\Auth;
use CaddyPanel\Bootstrap;
use CaddyPanel\Config;

final class AuthController
{
    public static function me(Config $config, array $params): array
    {
        $hasUser = (int) \CaddyPanel\Db::get()->query('SELECT COUNT(*) c FROM users')->fetch()['c'] > 0;
        return ['data' => [
            'user' => Auth::current($config),
            'csrf' => Auth::csrfToken(),
            'local' => Auth::isLocal(),
            'has_user' => $hasUser,
            'installed' => is_file(APP_ROOT . '/var/installed'),
        ]];
    }

    public static function login(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        return ['data' => [
            'user' => Auth::login((string) ($b['username'] ?? ''), (string) ($b['password'] ?? '')),
        ]];
    }

    public static function init(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        return ['data' => [
            'user' => Auth::setup(
                (string) ($b['token'] ?? ''),
                (string) ($b['username'] ?? ''),
                (string) ($b['password'] ?? ''),
                $config
            ),
        ]];
    }

    public static function logout(Config $config, array $params): array
    {
        Auth::logout();
        return ['data' => ['ok' => true]];
    }

    public static function password(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        Auth::changePassword((string) ($b['old'] ?? ''), (string) ($b['new'] ?? ''));
        return ['data' => ['ok' => true]];
    }
}
