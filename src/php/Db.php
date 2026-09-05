<?php
declare(strict_types=1);

namespace CaddyPanel;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $dir = APP_ROOT . '/var';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            self::$pdo = new PDO('sqlite:' . $dir . '/panel.sqlite');
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA journal_mode=WAL');
            self::$pdo->exec('PRAGMA foreign_keys=ON');
            self::schema(self::$pdo);
        }
        return self::$pdo;
    }

    private static function schema(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS sites(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    mode TEXT NOT NULL DEFAULT 'static',
    form_json TEXT NOT NULL DEFAULT '{}',
    raw TEXT NOT NULL DEFAULT '',
    advanced INTEGER NOT NULL DEFAULT 0,
    file_hash TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS settings(
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS audit(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ts TEXT NOT NULL,
    action TEXT NOT NULL,
    target TEXT NOT NULL,
    result TEXT NOT NULL,
    by TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS login_attempts(
    ip TEXT PRIMARY KEY,
    failed INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT
);
CREATE TABLE IF NOT EXISTS api_tokens(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT UNIQUE NOT NULL,
    scope TEXT NOT NULL DEFAULT 'read',
    created_at TEXT NOT NULL,
    last_used TEXT
);
SQL);
    }
}
