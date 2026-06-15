<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;

class LoginAttempt
{
    private const MAX_ATTEMPTS   = 5;
    private const WINDOW_SECONDS = 15 * 60; // 15 minutes
    private static bool $tableChecked = false;

    public static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        Database::getInstance()->exec("
            CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `username`     VARCHAR(50)  NOT NULL DEFAULT '',
                `ip`           VARCHAR(45)  NOT NULL,
                `attempted_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_ip_time`       (`ip`, `attempted_at`),
                KEY `idx_username_time` (`username`, `attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        self::$tableChecked = true;
    }

    public static function isBlocked(string $ip): bool
    {
        self::ensureTable();
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $stmt  = Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?'
        );
        $stmt->execute([$ip, $since]);
        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function record(string $ip, string $username): void
    {
        self::ensureTable();
        $pdo = Database::getInstance();
        // Prune entries older than 24 hours
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $pdo->prepare('INSERT INTO login_attempts (ip, username) VALUES (?, ?)')->execute([$ip, $username]);
    }

    public static function clearForIp(string $ip): void
    {
        self::ensureTable();
        Database::getInstance()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    }
}
