<?php
declare(strict_types=1);

namespace CaddyPanel;

final class ApplyService
{
    public static function applySites(Config $config): array
    {
        $sm = new SiteManager();
        $prevMain = self::readMain($config);
        $prevFiles = self::fragmentSnapshot($config);
        Recovery::create($config, 'auto-apply');

        try {
            $sm->writeFragments($config);
            $content = self::buildManaged($config);
            file_put_contents($config->caddyfilePath(), $content);
            self::adaptAndLoad($config, $content, $prevMain, $prevFiles);
            Recovery::markLastGood($config, 'apply-sites');
            Audit::log('saveSites', 'Caddyfile', 'ok');
            return ['adapted' => true, 'content' => $content];
        } catch (ApiError $e) {
            self::rollback($config, $prevMain, $prevFiles);
            throw $e;
        }
    }

    public static function applyRaw(Config $config, string $content): array
    {
        $prevMain = self::readMain($config);
        Recovery::create($config, 'auto-apply-custom');
        try {
            file_put_contents($config->caddyfilePath(), $content);
            self::adaptAndLoad($config, $content, $prevMain, []);
            Recovery::markLastGood($config, 'apply-custom');
            Audit::log('saveCustom', 'Caddyfile', 'ok');
            return ['adapted' => true, 'content' => $content];
        } catch (ApiError $e) {
            self::rollback($config, $prevMain, []);
            throw $e;
        }
    }

    public static function adaptOnly(Config $config, string $content): array
    {
        $admin = self::admin($config);
        $r = $admin->adapt($content);
        if (!$r['ok']) {
            throw new ApiError('adapt_failed', 422, ['detail' => (string) $r['error']]);
        }
        return ['adapted' => $r['body'], 'content' => $content];
    }

    public static function buildManaged(Config $config): string
    {
        $sm = new SiteManager();
        $global = $sm->globalContent($config);
        $style = Settings::get('caddyfile_style', 'import');
        if ($style === 'concat') {
            return CaddyfileBuilder::assemble($global, $sm->fragmentsFor($config));
        }
        return CaddyfileBuilder::assembleImport($global, $config->fragmentsDir());
    }

    private static function adaptAndLoad(Config $config, string $content, ?string $prevMain, array $prevFiles): void
    {
        $admin = self::admin($config);
        $adapted = $admin->adapt($content);
        if (!$adapted['ok']) {
            throw new ApiError('adapt_failed', 422, ['detail' => (string) $adapted['error']]);
        }
        $loaded = $admin->load($content);
        if (!$loaded['ok']) {
            throw new ApiError('load_failed', 422, ['detail' => (string) $loaded['error']]);
        }
    }

    private static function admin(Config $config): AdminClient
    {
        return new AdminClient((string) $config->get('caddy.admin_api', 'http://127.0.0.1:2019'));
    }

    private static function readMain(Config $config): ?string
    {
        $main = $config->caddyfilePath();
        return is_file($main) ? (string) file_get_contents($main) : null;
    }

    private static function fragmentSnapshot(Config $config): array
    {
        $out = [];
        $dir = $config->fragmentsDir();
        foreach (glob($dir . '/*.caddy') ?: [] as $path) {
            $out[$path] = is_file($path) ? (string) file_get_contents($path) : null;
        }
        return $out;
    }

    private static function rollback(Config $config, ?string $prevMain, array $prevFiles): void
    {
        $main = $config->caddyfilePath();
        if ($prevMain === null) {
            if (is_file($main)) {
                @unlink($main);
            }
        } else {
            file_put_contents($main, $prevMain);
        }
        foreach ($prevFiles as $path => $content) {
            if ($content === null) {
                if (is_file($path)) {
                    @unlink($path);
                }
            } else {
                file_put_contents($path, $content);
            }
        }
    }
}
