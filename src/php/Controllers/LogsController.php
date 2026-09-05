<?php
declare(strict_types=1);

namespace CaddyPanel\Controllers;

use CaddyPanel\Config;

final class LogsController
{
    public static function index(Config $config, array $params): array
    {
        $file = trim((string) $config->get('caddy.log_file', ''));
        $fallback = APP_ROOT . '/var/logs/caddy-access.json';
        if ($file === '' && is_file($fallback)) {
            $file = $fallback;
        }
        if ($file === '' || !is_file($file)) {
            return ['data' => ['configured' => false, 'file' => '', 'lines' => []]];
        }

        $want = max(1, min(1000, (int) ($_GET['lines'] ?? 100)));
        $q = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $raw = self::tail($file, $want * 4);
        $out = [];

        foreach (preg_split('/\r?\n/', $raw) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $j = json_decode($line, true);
            if (!is_array($j)) {
                continue;
            }
            $req = $j['request'] ?? [];
            $row = [
                'ts' => (float) ($j['ts'] ?? 0),
                'time' => isset($j['ts']) ? date('H:i:s', (int) $j['ts']) : '',
                'level' => (string) ($j['level'] ?? 'info'),
                'msg' => (string) ($j['msg'] ?? ''),
                'ip' => (string) ($req['remote_ip'] ?? ''),
                'method' => (string) ($req['method'] ?? ''),
                'host' => (string) ($req['host'] ?? ''),
                'uri' => (string) ($req['uri'] ?? ''),
                'status' => (int) ($j['status'] ?? 0),
                'duration' => (float) ($j['duration'] ?? 0),
                'size' => (int) ($j['size'] ?? 0),
            ];
            if ($status !== '' && (string) $row['status'] !== $status) {
                continue;
            }
            if ($q !== '' && stripos($row['uri'] . $row['host'] . $row['ip'], $q) === false) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $want) {
                break;
            }
        }
        return ['data' => ['configured' => true, 'file' => $file, 'lines' => array_reverse($out)]];
    }

    private static function tail(string $file, int $maxLines): string
    {
        $size = (int) filesize($file);
        $read = min($size, 1024 * 1024);
        $fp = fopen($file, 'rb');
        if (!$fp) {
            return '';
        }
        fseek($fp, $size - $read);
        $data = (string) stream_get_contents($fp);
        fclose($fp);
        // 仅当发生截断时丢弃可能的半行（从第一个换行后开始）
        $nl = strpos($data, "\n");
        if ($nl !== false && $size > $read) {
            $data = substr($data, $nl + 1);
        }
        return $data;
    }
}
