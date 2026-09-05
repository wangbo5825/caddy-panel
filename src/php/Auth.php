<?php
declare(strict_types=1);

namespace CaddyPanel;

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = APP_ROOT . '/var/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        session_save_path($dir);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('caddy_panel_sess');
        session_start();
    }

    public static function isLocal(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return $ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost';
    }

    public static function current(Config $config): ?array
    {
        self::startSession();
        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        if (self::isLocal() && $config->get('auth.allow_local_auto_login', true)) {
            $user = ['username' => 'admin', 'local' => true];
            $_SESSION['user'] = $user;
            Audit::log('localAuto', 'local', 'ok', 'admin');
            return $user;
        }
        return null;
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function requireAuth(Config $config): array
    {
        $user = self::current($config);
        if (!$user) {
            throw new ApiError('not_authenticated', 401);
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!hash_equals(self::csrfToken(), $sent)) {
                throw new ApiError('csrf_failed', 403);
            }
        }
        return $user;
    }

    public static function login(string $username, string $password): array
    {
        self::startSession();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $stmt = Db::get()->prepare('SELECT failed, locked_until FROM login_attempts WHERE ip=?');
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        if ($row && $row['locked_until'] && strtotime((string) $row['locked_until']) > time()) {
            throw new ApiError('rate_limited', 429);
        }

        $stmt = Db::get()->prepare('SELECT id, username, password_hash FROM users WHERE username=?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $failed = ((int) ($row['failed'] ?? 0)) + 1;
            $locked = $failed >= 5 ? date('c', time() + 900) : null;
            Db::get()->prepare(
                'INSERT INTO login_attempts(ip, failed, locked_until) VALUES (?, ?, ?)
                 ON CONFLICT(ip) DO UPDATE SET failed=excluded.failed, locked_until=excluded.locked_until'
            )->execute([$ip, $failed, $locked]);
            Audit::log('loginFailed', (string) $username, 'fail', (string) $username);
            throw new ApiError('bad_credentials', 401);
        }

        Db::get()->prepare('DELETE FROM login_attempts WHERE ip=?')->execute([$ip]);
        $_SESSION['user'] = ['username' => $user['username'], 'local' => false];
        Audit::log('login', (string) $user['username'], 'ok', (string) $user['username']);
        return $_SESSION['user'];
    }

    public static function setup(string $token, string $username, string $password, Config $config): array
    {
        self::startSession();
        $count = (int) Db::get()->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
        if ($count > 0) {
            throw new ApiError('account_exists', 409);
        }
        $expected = (string) $config->get('auth.setup_token', '');
        $tokenFile = APP_ROOT . '/var/setup.token';
        if ($expected === '' && is_file($tokenFile)) {
            $expected = trim((string) file_get_contents($tokenFile));
        }
        if (!self::isLocal() && ($expected === '' || !hash_equals($expected, $token))) {
            throw new ApiError('setup_token_invalid', 403);
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{2,32}$/', $username)) {
            throw new ApiError('username_invalid', 422);
        }
        if (strlen($password) < 8) {
            throw new ApiError('password_short', 422);
        }
        Db::get()->prepare('INSERT INTO users(username, password_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_ARGON2ID), date('c')]);
        if (is_file($tokenFile)) {
            @unlink($tokenFile);
        }
        $_SESSION['user'] = ['username' => $username, 'local' => false];
        Audit::log('init', $username, 'ok', $username);
        return $_SESSION['user'];
    }

    public static function changePassword(string $old, string $new): void
    {
        self::startSession();
        $user = $_SESSION['user'] ?? null;
        $username = (string) ($user['username'] ?? 'admin');
        $stmt = Db::get()->prepare('SELECT password_hash FROM users WHERE username=?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new ApiError('account_not_found', 404);
        }
        if (!password_verify($old, $row['password_hash'])) {
            throw new ApiError('password_wrong', 403);
        }
        if (strlen($new) < 8) {
            throw new ApiError('password_short', 422);
        }
        Db::get()->prepare('UPDATE users SET password_hash=? WHERE username=?')
            ->execute([password_hash($new, PASSWORD_ARGON2ID), $username]);
        Audit::log('changePwd', $username, 'ok', $username);
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
