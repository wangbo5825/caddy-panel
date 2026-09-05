<?php
declare(strict_types=1);

namespace CaddyPanel;

final class Bootstrap
{
    private static array $routes = [
        ['GET', '/api/status', [Controllers\StatusController::class, 'status']],
        ['GET', '/api/audit', [Controllers\StatusController::class, 'audit']],
        ['POST', '/api/auth/login', [Controllers\AuthController::class, 'login'], false],
        ['POST', '/api/auth/init', [Controllers\AuthController::class, 'init'], false],
        ['POST', '/api/auth/logout', [Controllers\AuthController::class, 'logout']],
        ['GET', '/api/auth/me', [Controllers\AuthController::class, 'me'], false],
        ['POST', '/api/auth/password', [Controllers\AuthController::class, 'password']],
        ['GET', '/api/sites', [Controllers\SitesController::class, 'index']],
        ['POST', '/api/sites', [Controllers\SitesController::class, 'create']],
        ['GET', '/api/sites/{id}', [Controllers\SitesController::class, 'show']],
        ['PUT', '/api/sites/{id}', [Controllers\SitesController::class, 'update']],
        ['DELETE', '/api/sites/{id}', [Controllers\SitesController::class, 'delete']],
        ['POST', '/api/sites/{id}/toggle', [Controllers\SitesController::class, 'toggle']],
        ['GET', '/api/caddyfile', [Controllers\CaddyfileController::class, 'show']],
        ['POST', '/api/caddyfile/check', [Controllers\CaddyfileController::class, 'check']],
        ['POST', '/api/caddyfile/apply', [Controllers\CaddyfileController::class, 'apply']],
        ['GET', '/api/certs', [Controllers\CertsController::class, 'index']],
        ['GET', '/api/logs', [Controllers\LogsController::class, 'index']],
        ['GET', '/api/settings', [Controllers\SettingsController::class, 'index']],
        ['PUT', '/api/settings', [Controllers\SettingsController::class, 'update']],
        ['POST', '/api/settings/test', [Controllers\SettingsController::class, 'test']],
        ['POST', '/api/tokens', [Controllers\SettingsController::class, 'generateToken']],
    ];

    public static function run(string $appRoot): void
    {
        $config = Config::load($appRoot);
        self::ensureDirs($appRoot);

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (strpos($path, '/api/') !== 0) {
            self::serveApp($appRoot, $path);
            return;
        }

        try {
            foreach (self::$routes as $route) {
                [$m, $pattern, $handler] = $route;
                $auth = $route[3] ?? true;
                if ($m !== $method) {
                    continue;
                }
                $regex = '#^' . str_replace('{id}', '([^/]+)', $pattern) . '$#';
                if (!preg_match($regex, $path, $matches)) {
                    continue;
                }
                if ($auth) {
                    Auth::requireAuth($config);
                }
                $params = [];
                if (isset($matches[1])) {
                    $params['id'] = $matches[1];
                }
                $result = call_user_func($handler, $config, $params);
                self::json(is_array($result) && array_key_exists('data', $result) ? $result['data'] : $result, $result['status'] ?? 200);
                return;
            }
            throw new ApiError('api_not_found', 404);
        } catch (ApiError $e) {
            self::json([
                'error' => L10n::text($e->errorCode, $e->params),
                'code' => $e->errorCode,
            ], $e->getCode() !== 0 ? $e->getCode() : 400);
        } catch (\Throwable $e) {
            self::json([
                'error' => L10n::text('server_error', ['detail' => $e->getMessage()]),
                'code' => 'server_error',
            ], 500);
        }
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function ensureDirs(string $appRoot): void
    {
        foreach ([
            $appRoot . '/var',
            $appRoot . '/var/backups',
            $appRoot . '/var/backups/config',
            $appRoot . '/var/logs',
            $appRoot . '/var/sessions',
            $appRoot . '/etc',
            $appRoot . '/etc/caddy.d',
        ] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }

    private static function serveApp(string $appRoot, string $path): void
    {
        // php -S 路由模式下：若请求的是 public 内真实存在的文件（install.php、构建产物等），直接输出
        if ($path !== '/') {
            $file = $appRoot . '/public' . $path;
            if (is_file($file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if ($ext === 'php') {
                    // 防御：PHP 文件应由服务器执行，禁止静态输出源码
                    return;
                }
                $mime = [
                    'html' => 'text/html; charset=utf-8',
                    'js' => 'application/javascript',
                    'css' => 'text/css; charset=utf-8',
                    'json' => 'application/json',
                    'svg' => 'image/svg+xml',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'ico' => 'image/x-icon',
                    'woff2' => 'font/woff2',
                    'map' => 'application/json',
                ];
                if (isset($mime[$ext])) {
                    header('Content-Type: ' . $mime[$ext]);
                }
                readfile($file);
                return;
            }
        }
        $index = $appRoot . '/public/index.html';
        if (is_file($index)) {
            readfile($index);
            return;
        }
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>caddy-panel</title>'
            . '<body style="font-family:system-ui;background:#0d1117;color:#e4eaf2;display:grid;place-items:center;height:100vh">'
            . '<div style="text-align:center"><h1>caddy-panel</h1>'
            . '<p>后端已就绪，前端尚未构建。</p><p>进入 <code>web/</code> 执行 <code>npm install && npm run build</code> 后刷新。</p></div>';
    }
}
