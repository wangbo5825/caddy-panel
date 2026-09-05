<?php
declare(strict_types=1);

namespace CaddyPanel\Controllers;

use CaddyPanel\AdminClient;
use CaddyPanel\ApiError;
use CaddyPanel\Audit;
use CaddyPanel\Bootstrap;
use CaddyPanel\Config;
use CaddyPanel\Db;
use CaddyPanel\Settings;

final class SettingsController
{
    public static function index(Config $config, array $params): array
    {
        $userCount = (int) Db::get()->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
        return ['data' => [
            'local_auto_login' => (bool) $config->get('auth.allow_local_auto_login', true),
            'admin_api' => (string) $config->get('caddy.admin_api', 'http://127.0.0.1:2019'),
            'caddyfile' => $config->caddyfilePath(),
            'fragments_dir' => $config->fragmentsDir(),
            'global_file' => $config->globalFile(),
            'data_dir' => (string) $config->get('caddy.data_dir', ''),
            'log_file' => (string) $config->get('caddy.log_file', ''),
            'panel_name' => (string) $config->get('panel.name', 'caddy-panel'),
            'managed_mode' => Settings::bool('managed_mode', true),
            'allow_php' => Settings::bool('allow_php', true),
            'caddyfile_style' => Settings::get('caddyfile_style', 'import'),
            'has_user' => $userCount > 0,
            'installed' => is_file(APP_ROOT . '/var/installed'),
        ]];
    }

    public static function update(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        if (array_key_exists('local_auto_login', $b)) {
            $config->set('auth.allow_local_auto_login', !empty($b['local_auto_login']));
        }
        if (isset($b['admin_api'])) {
            $api = trim((string) $b['admin_api']);
            if ($api !== '' && !preg_match('#^(https?|unix)://#i', $api)) {
                throw new ApiError('admin_api_invalid', 422);
            }
            $config->set('caddy.admin_api', $api);
        }
        if (isset($b['data_dir'])) {
            $config->set('caddy.data_dir', trim((string) $b['data_dir']));
        }
        if (isset($b['log_file'])) {
            $config->set('caddy.log_file', trim((string) $b['log_file']));
        }
        if (isset($b['panel_name'])) {
            $config->set('panel.name', trim((string) $b['panel_name']));
        }
        if (isset($b['managed_mode'])) {
            Settings::set('managed_mode', !empty($b['managed_mode']) ? '1' : '0');
        }
        if (isset($b['allow_php'])) {
            Settings::set('allow_php', !empty($b['allow_php']) ? '1' : '0');
        }
        if (isset($b['caddyfile_style'])) {
            $style = (string) $b['caddyfile_style'];
            if (!in_array($style, ['import', 'concat'], true)) {
                throw new ApiError('style_invalid', 422);
            }
            Settings::set('caddyfile_style', $style);
        }
        if (!$config->save()) {
            throw new ApiError('config_not_writable', 500);
        }
        Audit::log('updateSettings', 'settings', 'ok');
        return ['data' => ['ok' => true]];
    }

    public static function test(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        $base = trim((string) ($b['admin_api'] ?? $config->get('caddy.admin_api', 'http://127.0.0.1:2019')));
        $r = (new AdminClient($base))->config();
        return ['data' => ['ok' => $r['ok'], 'error' => $r['error'], 'latency' => $r['latency']]];
    }

    public static function generateToken(Config $config, array $params): array
    {
        $token = 'cp_' . bin2hex(random_bytes(24));
        Db::get()->prepare('INSERT INTO api_tokens(token_hash, scope, created_at) VALUES (?, ?, ?)')
            ->execute([hash('sha256', $token), 'admin', date('c')]);
        Audit::log('token', 'API', 'ok');
        return ['data' => ['token' => $token, 'scope' => 'admin', 'note' => '仅显示一次，请妥善保存']];
    }
}
