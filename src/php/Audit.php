<?php
declare(strict_types=1);

namespace CaddyPanel;

final class Audit
{
    public static function log(string $action, string $target, string $result, string $by = 'unknown'): void
    {
        try {
            Db::get()->prepare(
                'INSERT INTO audit(ts, action, target, result, by) VALUES (?, ?, ?, ?, ?)'
            )->execute([date('c'), $action, $target, $result, $by]);
        } catch (\Throwable $e) {
            // 审计失败不阻断主流程
        }
    }

    public static function recent(int $limit = 20): array
    {
        return Db::get()->query(
            'SELECT id, ts, action, target, result, by FROM audit ORDER BY id DESC LIMIT ' . max(1, (int) $limit)
        )->fetchAll();
    }
}
