<?php
declare(strict_types=1);

require __DIR__ . '/panel.bootstrap.php';

// php -S 路由模式：请求真实存在的 PHP 文件时交还给内置服务器执行（如 install.php）
if (PHP_SAPI === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $target = __DIR__ . $uriPath;
    if ($uriPath !== '/index.php' && is_file($target) && strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'php') {
        return false;
    }
}

// 路径合法性检查：必须是绝对路径且可读
$appRootReal = realpath(APP_ROOT);
if ($appRootReal === false) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    exit('APP_ROOT 路径无法解析：' . htmlspecialchars((string) APP_ROOT) . '。请在 webroot/panel.bootstrap.php 中填写正确的绝对路径。');
}

$installed = is_file($appRootReal . '/var/installed');
$isInstallRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/install.php') !== false);

if (!$installed && !$isInstallRequest) {
    header('Location: install.php', true, 302);
    exit;
}

require $appRootReal . '/src/php/autoload.php';
\CaddyPanel\Bootstrap::run($appRootReal);
