<?php
declare(strict_types=1);

namespace CaddyPanel\Controllers;

use CaddyPanel\Config;

final class CertsController
{
    public static function index(Config $config, array $params): array
    {
        return ['data' => self::list($config)];
    }

    public static function list(Config $config): array
    {
        $dataDir = self::findDataDir($config);
        $certs = [];
        if ($dataDir !== '') {
            $certs = self::scan($dataDir);
        }
        $now = time();
        $expiring = 0;
        foreach ($certs as $c) {
            if ($c['expires'] && $c['expires'] - $now < 14 * 86400 && $c['expires'] > $now) {
                $expiring++;
            }
        }
        return [
            'configured' => $dataDir !== '',
            'data_dir' => $dataDir,
            'certs' => $certs,
            'expiring' => $expiring,
        ];
    }

    private static function findDataDir(Config $config): string
    {
        $d = trim((string) $config->get('caddy.data_dir', ''));
        if ($d !== '' && is_dir($d)) {
            return rtrim($d, '/\\');
        }
        $candidates = [];
        $xdg = getenv('XDG_DATA_HOME');
        if ($xdg) {
            $candidates[] = rtrim($xdg, '/\\') . '/caddy';
        }
        $local = getenv('LOCALAPPDATA');
        if ($local) {
            $candidates[] = rtrim($local, '/\\') . '/caddy';
        }
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        if ($home) {
            $candidates[] = rtrim($home, '/\\') . '/.local/share/caddy';
        }
        foreach ($candidates as $c) {
            if (is_dir($c . '/certificates')) {
                return $c;
            }
        }
        return '';
    }

    private static function scan(string $dataDir): array
    {
        $certsDir = $dataDir . '/certificates';
        if (!is_dir($certsDir)) {
            return [];
        }
        $out = [];
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($certsDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($it->getDepth() > 8 || $file->getExtension() !== 'crt') {
                    continue;
                }
                $parsed = self::parse((string) $file->getPathname());
                if ($parsed) {
                    $out[] = $parsed;
                }
            }
        } catch (\Throwable $e) {
            // 忽略不可读证书目录
        }
        return $out;
    }

    private static function parse(string $file): ?array
    {
        $pem = @file_get_contents($file);
        if ($pem === false) {
            return null;
        }
        $cert = @openssl_x509_read($pem);
        if ($cert === false) {
            return null;
        }
        $info = @openssl_x509_parse($cert);
        if (!is_array($info)) {
            return null;
        }
        $cn = $info['subject']['CN'] ?? '';
        $issuer = $info['issuer']['CN'] ?? '';
        $sanRaw = $info['extensions']['subjectAltName'] ?? '';
        $domains = [];
        foreach (explode(',', (string) $sanRaw) as $part) {
            if (preg_match('/^DNS:(.+)$/i', trim($part), $m)) {
                $domains[] = trim($m[1]);
            }
        }
        if (!$domains && $cn !== '') {
            $domains[] = $cn;
        }
        $name = $domains[0] ?? basename($file, '.crt');
        return [
            'domain' => $name,
            'alt_names' => $domains,
            'issuer' => $issuer,
            'not_before' => (int) ($info['validFrom_time_t'] ?? 0),
            'not_after' => (int) ($info['validTo_time_t'] ?? 0),
            'expires' => (int) ($info['validTo_time_t'] ?? 0),
            'file' => $file,
        ];
    }
}
