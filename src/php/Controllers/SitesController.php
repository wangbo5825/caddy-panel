<?php
declare(strict_types=1);

namespace CaddyPanel\Controllers;

use CaddyPanel\ApplyService;
use CaddyPanel\Audit;
use CaddyPanel\Bootstrap;
use CaddyPanel\Config;
use CaddyPanel\Db;
use CaddyPanel\SiteManager;

final class SitesController
{
    public static function index(Config $config, array $params): array
    {
        return ['data' => ['items' => (new SiteManager())->all()]];
    }

    public static function show(Config $config, array $params): array
    {
        $site = (new SiteManager())->find((int) $params['id']);
        if (!$site) {
            throw new \CaddyPanel\ApiError('站点不存在', 404);
        }
        $file = $config->fragmentsDir() . '/' . basename((string) $site['name']) . '.caddy';
        $disk = is_file($file) ? (string) file_get_contents($file) : null;
        $site['file'] = $disk;
        $site['external_modified'] = $disk !== null && hash('sha256', $disk) !== (string) $site['file_hash'];
        return ['data' => $site];
    }

    public static function create(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        $sm = new SiteManager();
        $site = $sm->create($b);
        try {
            ApplyService::applySites($config);
        } catch (\Throwable $ex) {
            $sm->delete((int) $site['id']);
            throw $ex;
        }
        $site = $sm->find((int) $site['id']);
        Audit::log('createSite', (string) $site['name'], 'ok');
        return ['data' => $site];
    }

    public static function update(Config $config, array $params): array
    {
        $b = Bootstrap::body();
        $sm = new SiteManager();
        $old = $sm->find((int) $params['id']);
        if (!$old) {
            throw new \CaddyPanel\ApiError('站点不存在', 404);
        }
        $site = $sm->update((int) $params['id'], $b);
        try {
            ApplyService::applySites($config);
        } catch (\Throwable $ex) {
            $sm->update((int) $params['id'], [
                'name' => $old['name'],
                'mode' => $old['mode'],
                'form' => $old['form'],
                'advanced' => (bool) $old['advanced'],
                'raw' => $old['raw'],
                'enabled' => (bool) $old['enabled'],
            ]);
            throw $ex;
        }
        $site = $sm->find((int) $params['id']);
        Audit::log('updateSite', (string) $site['name'], 'ok');
        return ['data' => $site];
    }

    public static function delete(Config $config, array $params): array
    {
        $sm = new SiteManager();
        $site = $sm->find((int) $params['id']);
        if (!$site) {
            throw new \CaddyPanel\ApiError('站点不存在', 404);
        }
        $sm->delete((int) $params['id']);
        try {
            ApplyService::applySites($config);
        } catch (\Throwable $ex) {
            Db::get()->prepare(
                'INSERT INTO sites(name, enabled, mode, form_json, raw, advanced, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $site['name'], $site['enabled'], $site['mode'],
                $site['form_json'], $site['raw'], $site['advanced'],
                $site['created_at'], date('c'),
            ]);
            throw $ex;
        }
        Audit::log('deleteSite', (string) $site['name'], 'ok');
        return ['data' => ['ok' => true]];
    }

    public static function toggle(Config $config, array $params): array
    {
        $sm = new SiteManager();
        $site = $sm->toggle((int) $params['id']);
        try {
            ApplyService::applySites($config);
        } catch (\Throwable $ex) {
            Db::get()->prepare('UPDATE sites SET enabled=? WHERE id=?')
                ->execute([$site['enabled'] ? 0 : 1, $params['id']]);
            throw $ex;
        }
        $site = $sm->find((int) $params['id']);
        Audit::log($site['enabled'] ? 'enableSite' : 'disableSite', (string) $site['name'], 'ok');
        return ['data' => $site];
    }
}
