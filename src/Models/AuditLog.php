<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;

class AuditLog
{
    private static bool $tableChecked = false;

    public static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        Database::getInstance()->exec("
            CREATE TABLE IF NOT EXISTS `audit_log` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `username`   VARCHAR(50)  NOT NULL DEFAULT '',
                `action`     VARCHAR(60)  NOT NULL,
                `detail`     TEXT,
                `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
                `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_username`  (`username`),
                KEY `idx_action`    (`action`),
                KEY `idx_created`   (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        self::$tableChecked = true;
    }

    public static function record(string $username, string $action, string $detail = '', string $ip = ''): void
    {
        self::ensureTable();
        if ($ip === '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        Database::getInstance()
            ->prepare('INSERT INTO audit_log (username, action, detail, ip) VALUES (?, ?, ?, ?)')
            ->execute([$username, $action, $detail, $ip]);
    }

    public static function recent(int $limit = 200, string $filterAction = ''): array
    {
        self::ensureTable();
        if ($filterAction !== '') {
            $stmt = Database::getInstance()->prepare(
                'SELECT * FROM audit_log WHERE action LIKE ? ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->execute([$filterAction . '%', $limit]);
        } else {
            $stmt = Database::getInstance()->prepare(
                'SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->execute([$limit]);
        }
        return $stmt->fetchAll();
    }

    public static function forUser(string $username, int $limit = 100): array
    {
        self::ensureTable();
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM audit_log WHERE username = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$username, $limit]);
        return $stmt->fetchAll();
    }
}
