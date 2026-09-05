<?php
declare(strict_types=1);

namespace CaddyPanel;

final class Settings
{
    public static function get(string $key, string $default = ''): string
    {
        $stmt = Db::get()->prepare('SELECT value FROM settings WHERE key=?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string) $row['value'] : $default;
    }

    public static function set(string $key, string $value): void
    {
        Db::get()->prepare(
            'INSERT INTO settings(key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value'
        )->execute([$key, $value]);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default ? '1' : '0');
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
