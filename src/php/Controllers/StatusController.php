<?php
declare(strict_types=1);

namespace CaddyPanel\Controllers;

use CaddyPanel\AdminClient;
use CaddyPanel\Audit;
use CaddyPanel\Config;
use CaddyPanel\SiteManager;

final class StatusController
{
    public static function status(Config $config, array $params): array
    {
        $admin = new AdminClient((string) $config->get('caddy.admin_api', 'http://127.0.0.1:2019'));
        $r = $admin->config();
        $sites = (new SiteManager())->all();
        $certs = CertsController::list($config);
        $appRoot = $config->appRoot();

        return ['data' => [
            'caddy' => [
                'ok' => $r['ok'],
                'error' => $r['error'],
                'latency' => $r['latency'],
            ],
            'sites' => [
                'total' => count($sites),
                'enabled' => count(array_filter($sites, static fn($s) => (int) $s['enabled'] === 1)),
            ],
            'certs' => [
                'total' => count($certs['certs']),
                'expiring' => $certs['expiring'],
            ],
            'disk' => [
                'free' => (float) (@disk_free_space($appRoot) ?: 0),
                'total' => (float) (@disk_total_space($appRoot) ?: 0),
                'var' => self::dirSize($appRoot . '/var'),
            ],
            'app' => [
                'root' => $appRoot,
            ],
        ]];
    }

    public static function audit(Config $config, array $params): array
    {
        return ['data' => ['items' => Audit::recent(50)]];
    }

    private static function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            $depth = 0;
            foreach ($it as $file) {
                if ($it->getDepth() > 8) {
                    $it->next();
                    continue;
                }
                $size += $file->getSize();
            }
        } catch (\Throwable $e) {
            // 忽略不可读目录
        }
        return $size;
    }
}
