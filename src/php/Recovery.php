<?php
declare(strict_types=1);

namespace CaddyPanel;

/**
 * 紧急恢复：全量配置快照（主 Caddyfile + 全局段 + caddy.d 片段）+ last-good 标记。
 *
 * 适用于 FrankenPHP 无法启动（Caddy 配置损坏）等 Web 不可达场景：
 * 恢复只做磁盘文件操作，不依赖 Admin API 或 Web 进程，
 * 恢复后下次启动 FrankenPHP/Caddy 即使用该配置。
 */
final class Recovery
{
    private const BACKUP_DIR = '/var/backups/config';
    private const LAST_GOOD_DIR = '/var/last-good';
    private const KEEP = 30;

    public static function create(Config $config, string $reason = 'manual'): string
    {
        $base = APP_ROOT . self::BACKUP_DIR;
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        $id = date('Ymd-His');
        $suffix = 1;
        $dir = $base . '/' . $id;
        while (is_dir($dir)) {
            $dir = $base . '/' . $id . '-' . (++$suffix);
        }
        @mkdir($dir, 0775, true);
        self::exportTo($config, $dir, $reason);
        self::prune($base);
        return basename($dir);
    }

    public static function markLastGood(Config $config, string $reason = 'applied'): void
    {
        $target = APP_ROOT . self::LAST_GOOD_DIR;
        $tmp = APP_ROOT . '/var/last-good.tmp';
        if (is_dir($tmp)) {
            self::rmdirRecursive($tmp);
        }
        @mkdir($tmp, 0775, true);
        self::exportTo($config, $tmp, $reason);
        if (is_dir($target)) {
            self::rmdirRecursive($target);
        }
        if (!@rename($tmp, $target)) {
            // Windows 下重命名目录失败时的兜底
            self::rmdirRecursive($tmp);
        }
    }

    public static function hasLastGood(): bool
    {
        return is_dir(APP_ROOT . self::LAST_GOOD_DIR)
            && is_file(APP_ROOT . self::LAST_GOOD_DIR . '/Caddyfile');
    }

    public static function lastGood(): ?string
    {
        return self::hasLastGood() ? APP_ROOT . self::LAST_GOOD_DIR : null;
    }

    public static function latest(): ?string
    {
        $dirs = self::snapshotDirs();
        return $dirs[0] ?? null;
    }

    /** @return array<int, array{id:string, created_at:string, reason:string, files:int}> */
    public static function list(): array
    {
        $out = [];
        foreach (self::snapshotDirs() as $dir) {
            $manifest = self::manifest($dir);
            $out[] = [
                'id' => basename($dir),
                'created_at' => $manifest['created_at'] ?? date('c', (int) filemtime($dir)),
                'reason' => $manifest['reason'] ?? 'unknown',
                'files' => count(glob($dir . '/*') ?: []),
            ];
        }
        return $out;
    }

    public static function restore(Config $config, string $id): array
    {
        $src = $id === 'last-good' ? self::lastGood() : self::resolveDir($id);
        if ($src === null || !is_dir($src)) {
            throw new ApiError('snapshot_not_found', 404, ['id' => $id]);
        }
        $main = $src . '/Caddyfile';
        if (!is_file($main)) {
            throw new ApiError('snapshot_invalid', 422, ['id' => $id]);
        }

        file_put_contents($config->caddyfilePath(), (string) file_get_contents($main));
        $globalSrc = $src . '/global.caddy';
        if (is_file($globalSrc)) {
            file_put_contents($config->globalFile(), (string) file_get_contents($globalSrc));
        }

        // 替换片段目录：清空现有 *.caddy，再从快照复制
        $fragDir = $config->fragmentsDir();
        if (!is_dir($fragDir)) {
            @mkdir($fragDir, 0775, true);
        }
        foreach (glob($fragDir . '/*.caddy') ?: [] as $old) {
            @unlink($old);
        }
        foreach (glob($src . '/caddy.d/*.caddy') ?: [] as $frag) {
            copy($frag, $fragDir . '/' . basename($frag));
        }

        $manifest = self::manifest($src);
        Audit::log('emergencyRestore', (string) basename($src), 'ok', 'cli');
        return [
            'id' => basename($src),
            'created_at' => $manifest['created_at'] ?? '',
            'reason' => $manifest['reason'] ?? '',
            'main' => $config->caddyfilePath(),
            'fragments' => count(glob($src . '/caddy.d/*.caddy') ?: []),
        ];
    }

    /** 结构自检：不依赖 Admin API；有本地 caddy/frankenphp 时可用 --verify 做真适配 */
    public static function doctor(Config $config): array
    {
        $issues = [];
        $warnings = [];
        $main = $config->caddyfilePath();
        if (!is_file($main)) {
            $issues[] = 'main-caddyfile-missing';
        } else {
            $content = (string) file_get_contents($main);
            if (preg_match('/^\s*import\s+(.+)$/m', $content, $m)) {
                $frags = glob($config->fragmentsDir() . '/*.caddy') ?: [];
                if (!$frags) {
                    $issues[] = 'import-without-fragments';
                }
            }
        }
        if (!self::hasLastGood()) {
            $warnings[] = 'no-last-good';
        }
        return ['ok' => !$issues, 'issues' => $issues, 'warnings' => $warnings];
    }

    private static function exportTo(Config $config, string $dir, string $reason): void
    {
        $main = $config->caddyfilePath();
        if (is_file($main)) {
            copy($main, $dir . '/Caddyfile');
        }
        $global = $config->globalFile();
        if (is_file($global)) {
            copy($global, $dir . '/global.caddy');
        }
        $fragDest = $dir . '/caddy.d';
        @mkdir($fragDest, 0775, true);
        $fragDir = $config->fragmentsDir();
        foreach (glob($fragDir . '/*.caddy') ?: [] as $frag) {
            copy($frag, $fragDest . '/' . basename($frag));
        }
        file_put_contents($dir . '/manifest.json', json_encode([
            'created_at' => date('c'),
            'reason' => $reason,
            'style' => Settings::get('caddyfile_style', 'import'),
            'app' => 'caddy-panel',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** @return string[] */
    private static function snapshotDirs(): array
    {
        $base = APP_ROOT . self::BACKUP_DIR;
        $dirs = glob($base . '/*', GLOB_ONLYDIR) ?: [];
        usort($dirs, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        return $dirs;
    }

    private static function resolveDir(string $id): ?string
    {
        foreach (self::snapshotDirs() as $dir) {
            if (basename($dir) === $id) {
                return $dir;
            }
        }
        return null;
    }

    private static function manifest(string $dir): array
    {
        $f = $dir . '/manifest.json';
        return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }

    private static function prune(string $base): void
    {
        $dirs = glob($base . '/*', GLOB_ONLYDIR) ?: [];
        usort($dirs, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($dirs, self::KEEP) as $old) {
            self::rmdirRecursive($old);
        }
    }

    private static function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            if ($f->isDir()) {
                @rmdir($f->getPathname());
            } else {
                @unlink($f->getPathname());
            }
        }
        @rmdir($dir);
    }
}
