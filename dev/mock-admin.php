<?php
declare(strict_types=1);

/**
 * Caddy Admin API 模拟器（仅开发测试用，不要用于生产）。
 *
 * 启动：php -S 127.0.0.1:2019 dev/mock-admin.php
 * 提供 /adapt、/load、/config/ 三个最小端点，便于无 Caddy 环境下验证面板流程。
 */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
header('Content-Type: application/json; charset=utf-8');

if ($method === 'POST' && $path === '/adapt') {
    $body = (string) file_get_contents('php://input');
    // 允许空配置 / 仅注释（真实 Caddy 允许空 Caddyfile）
    if (strpos($body, '{') !== false || preg_match('/^#/m', $body)) {
        echo json_encode([
            'warnings' => [],
            'result' => ['apps' => ['http' => ['servers' => ['srv0' => []]]]],
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'mock: 无法解析 Caddyfile（第 1 行）']);
    }
    exit;
}

if ($method === 'POST' && $path === '/load') {
    http_response_code(200);
    echo '';
    exit;
}

if ($method === 'GET' && $path === '/config/') {
    echo json_encode([
        'admin' => ['listen' => 'localhost:2019'],
        'apps' => ['http' => ['servers' => []]],
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'mock: endpoint not found: ' . $method . ' ' . $path]);
