<?php
declare(strict_types=1);

namespace CaddyPanel\Controllers;

use CaddyPanel\ApiError;
use CaddyPanel\ApplyService;
use CaddyPanel\Bootstrap;
use CaddyPanel\CaddyfileBuilder;
use CaddyPanel\Config;
use CaddyPanel\Settings;
use CaddyPanel\SiteManager;

final class CaddyfileController
{
    public static function show(Config $config, array $params): array
    {
        $mode = Settings::bool('managed_mode', true) ? 'managed' : 'custom';
        $sm = new SiteManager();
        $content = $mode === 'managed'
            ? ApplyService::buildManaged($config)
            : (is_file($config->caddyfilePath()) ? (string) file_get_contents($config->caddyfilePath()) : '');
        return ['data' => [
            'mode' => $mode,
            'global' => $sm->globalContent($config),
            'content' => $content,
            'caddyfile' => $config->caddyfilePath(),
            'style' => Settings::get('caddyfile_style', 'import'),
            'fragments_dir' => $config->fragmentsDir(),
        ]];
    }

    public static function check(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        $mode = (string) ($b['mode'] ?? (Settings::bool('managed_mode', true) ? 'managed' : 'custom'));

        if ($mode === 'custom' && isset($b['content'])) {
            $content = (string) $b['content'];
        } elseif (isset($b['global'])) {
            $sm = new SiteManager();
            $global = (string) $b['global'];
            $style = Settings::get('caddyfile_style', 'import');
            $content = $style === 'concat'
                ? CaddyfileBuilder::assemble($global, $sm->fragmentsFor($config))
                : CaddyfileBuilder::assembleImport($global, $config->fragmentsDir());
        } else {
            $content = ApplyService::buildManaged($config);
        }
        return ['data' => ApplyService::adaptOnly($config, $content)];
    }

    public static function apply(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        if (isset($b['mode'])) {
            Settings::set('managed_mode', $b['mode'] === 'managed' ? '1' : '0');
        }
        $mode = Settings::bool('managed_mode', true) ? 'managed' : 'custom';

        if ($mode === 'custom') {
            $content = (string) ($b['content'] ?? '');
            if (trim($content) === '') {
                throw new ApiError('caddyfile_empty', 422);
            }
            return ['data' => ApplyService::applyRaw($config, $content)];
        }

        if (isset($b['global'])) {
            file_put_contents($config->globalFile(), (string) $b['global']);
        }
        return ['data' => ApplyService::applySites($config)];
    }
}
