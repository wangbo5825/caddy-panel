<?php
declare(strict_types=1);

namespace CaddyPanel;

final class SiteManager
{
    public function all(): array
    {
        $rows = Db::get()->query(
            'SELECT id, name, enabled, mode, form_json, raw, advanced, file_hash, created_at, updated_at
             FROM sites ORDER BY name'
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['form'] = json_decode((string) $r['form_json'], true) ?: [];
        }
        return $rows;
    }

    public function find(int $id): ?array
    {
        $stmt = Db::get()->prepare(
            'SELECT id, name, enabled, mode, form_json, raw, advanced, file_hash, created_at, updated_at
             FROM sites WHERE id=?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['form'] = json_decode((string) $row['form_json'], true) ?: [];
        return $row;
    }

    public function create(array $input): array
    {
        $name = $this->normalizeName((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new ApiError('site_name_required', 422);
        }
        $mode = $this->normalizeMode((string) ($input['mode'] ?? 'static'));
        $form = $this->normalizeForm($mode, $input['form'] ?? []);
        $advanced = !empty($input['advanced']) ? 1 : 0;
        $raw = (string) ($input['raw'] ?? '');
        Db::get()->prepare(
            'INSERT INTO sites(name, enabled, mode, form_json, raw, advanced, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$name, !empty($input['enabled']) ? 1 : 1, $mode, json_encode($form, JSON_UNESCAPED_UNICODE), $raw, $advanced, date('c'), date('c')]);
        $id = (int) Db::get()->lastInsertId();
        return $this->find($id);
    }

    public function update(int $id, array $input): array
    {
        $site = $this->find($id);
        if (!$site) {
            throw new ApiError('site_not_found', 404);
        }
        $name = isset($input['name']) ? $this->normalizeName((string) $input['name']) : $site['name'];
        if ($name === '') {
            throw new ApiError('site_name_required', 422);
        }
        $mode = isset($input['mode']) ? $this->normalizeMode((string) $input['mode']) : $site['mode'];
        $form = isset($input['form']) ? $this->normalizeForm($mode, $input['form']) : ($site['form'] ?? []);
        $advanced = isset($input['advanced']) ? (!empty($input['advanced']) ? 1 : 0) : (int) $site['advanced'];
        $raw = isset($input['raw']) ? (string) $input['raw'] : (string) $site['raw'];
        $enabled = isset($input['enabled']) ? (!empty($input['enabled']) ? 1 : 0) : (int) $site['enabled'];
        Db::get()->prepare(
            'UPDATE sites SET name=?, enabled=?, mode=?, form_json=?, raw=?, advanced=?, updated_at=? WHERE id=?'
        )->execute([$name, $enabled, $mode, json_encode($form, JSON_UNESCAPED_UNICODE), $raw, $advanced, date('c'), $id]);
        return $this->find($id);
    }

    public function delete(int $id): void
    {
        $site = $this->find($id);
        if (!$site) {
            throw new ApiError('site_not_found', 404);
        }
        Db::get()->prepare('DELETE FROM sites WHERE id=?')->execute([$id]);
    }

    public function toggle(int $id): array
    {
        $site = $this->find($id);
        if (!$site) {
            throw new ApiError('site_not_found', 404);
        }
        $enabled = $site['enabled'] ? 0 : 1;
        Db::get()->prepare('UPDATE sites SET enabled=?, updated_at=? WHERE id=?')
            ->execute([$enabled, date('c'), $id]);
        return $this->find($id);
    }

    public function fragmentFor(array $site): string
    {
        if ((int) $site['advanced'] === 1 && trim((string) $site['raw']) !== '') {
            return trim((string) $site['raw']);
        }
        $form = $site['form'] ?? [];
        $domains = array_values(array_filter(array_map('trim', (array) ($form['domains'] ?? []))));
        if (!$domains) {
            throw new ApiError('site_domain_required', 422, ['name' => (string) $site['name']]);
        }
        $address = implode(' ', $domains);
        $mode = (string) $site['mode'];

        if ($mode === 'php') {
            $root = (string) ($form['root'] ?? 'htdocs/www');
            return $address . " {\n    import frampp_fingerprint_privacy\n    root * " . $root . "\n    php_server\n}";
        }
        if ($mode === 'static') {
            $root = (string) ($form['root'] ?? 'htdocs');
            return $address . " {\n    import frampp_fingerprint_privacy\n    root * " . $root . "\n    file_server\n}";
        }
        // reverse_proxy
        $upstreams = array_values(array_filter(array_map('trim', (array) ($form['upstreams'] ?? []))));
        $up = $upstreams ? implode(' ', $upstreams) : '127.0.0.1:9000';
        $lines = [$address . ' {', '    import frampp_fingerprint_privacy'];
        if (!empty($form['lb_policy'])) {
            $lines[] = '    reverse_proxy ' . $up . ' {';
            $lines[] = '        lb_policy ' . (string) $form['lb_policy'];
            $lines[] = '    }';
        } else {
            $lines[] = '    reverse_proxy ' . $up;
        }
        if (!empty($form['websocket'])) {
            $lines[] = '    @ws header Connection Upgrade';
            $lines[] = '    reverse_proxy @ws ' . $up;
        }
        $lines[] = '}';
        return implode("\n", $lines);
    }

    public function writeFragments(Config $config): array
    {
        $dir = $config->fragmentsDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $written = [];
        $sites = $this->all();
        $names = [];
        foreach ($sites as $site) {
            $names[] = basename((string) $site['name']);
            $path = $dir . '/' . basename((string) $site['name']) . '.caddy';
            if (!(int) $site['enabled']) {
                if (is_file($path)) {
                    @unlink($path);
                }
                continue;
            }
            $content = $this->fragmentFor($site);
            file_put_contents($path, $content . "\n");
            $hash = hash('sha256', $content);
            Db::get()->prepare('UPDATE sites SET file_hash=? WHERE id=?')->execute([$hash, $site['id']]);
            $written[] = basename($path);
        }
        // 清理不属于任何站点的遗留片段（如已删除站点）
        foreach (glob($dir . '/*.caddy') ?: [] as $orphan) {
            $base = basename($orphan, '.caddy');
            if (!in_array($base, $names, true)) {
                @unlink($orphan);
            }
        }
        return $written;
    }

    public function globalContent(Config $config): string
    {
        $file = $config->globalFile();
        if (is_file($file)) {
            return (string) file_get_contents($file);
        }
        return '';
    }

    public function fragmentsFor(Config $config): array
    {
        $dir = $config->fragmentsDir();
        $out = [];
        foreach ($this->all() as $site) {
            if (!(int) $site['enabled']) {
                continue;
            }
            $path = $dir . '/' . basename((string) $site['name']) . '.caddy';
            if (is_file($path)) {
                $out[] = (string) file_get_contents($path);
            } else {
                $out[] = $this->fragmentFor($site);
            }
        }
        return $out;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,63}$/', $name)) {
            throw new ApiError('site_name_invalid', 422);
        }
        return $name;
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, ['static', 'proxy', 'php'], true) ? $mode : 'static';
    }

    private function normalizeForm(string $mode, array $form): array
    {
        if ($mode === 'proxy') {
            $form['upstreams'] = array_values(array_filter(array_map('trim', (array) ($form['upstreams'] ?? []))));
            $form['lb_policy'] = in_array($form['lb_policy'] ?? '', ['round_robin', 'least_conn', 'random', 'ip_hash'], true)
                ? $form['lb_policy'] : '';
        } else {
            $form['root'] = trim((string) ($form['root'] ?? ''));
        }
        $form['domains'] = array_values(array_filter(array_map('trim', (array) ($form['domains'] ?? []))));
        return $form;
    }
}
