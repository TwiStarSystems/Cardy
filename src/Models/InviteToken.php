<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;

class InviteToken
{
    private static bool $tableChecked = false;

    private static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        Database::getInstance()->exec(
            "CREATE TABLE IF NOT EXISTS `invite_tokens` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `token`       VARCHAR(64)  NOT NULL,
                `created_by`  VARCHAR(50)  NOT NULL,
                `note`        VARCHAR(255) NOT NULL DEFAULT '',
                `max_uses`    INT UNSIGNED NOT NULL DEFAULT 1,
                `uses_count`  INT UNSIGNED NOT NULL DEFAULT 0,
                `expires_at`  TIMESTAMP    NULL DEFAULT NULL,
                `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `idx_token` (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$tableChecked = true;
    }

    /** Create a new invite token. Returns the raw token string. */
    public static function create(string $createdBy, string $note = '', int $maxUses = 1, ?int $expiryDays = null): string
    {
        self::ensureTable();
        $token     = bin2hex(random_bytes(24));
        $expiresAt = $expiryDays !== null ? date('Y-m-d H:i:s', time() + $expiryDays * 86400) : null;

        Database::getInstance()
            ->prepare('INSERT INTO invite_tokens (token, created_by, note, max_uses, expires_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$token, $createdBy, $note, max(1, $maxUses), $expiresAt]);

        return $token;
    }

    /** Verify a token is valid and not exhausted. Returns the token row or null. */
    public static function find(string $token): ?array
    {
        self::ensureTable();
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM invite_tokens
             WHERE token = ?
               AND uses_count < max_uses
               AND (expires_at IS NULL OR expires_at > NOW())'
        );
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    /** Increment the use count. Call after a successful sign-up. */
    public static function consume(string $token): void
    {
        self::ensureTable();
        Database::getInstance()
            ->prepare('UPDATE invite_tokens SET uses_count = uses_count + 1 WHERE token = ?')
            ->execute([$token]);
    }

    /** Return all tokens for the admin list, newest first. */
    public static function all(): array
    {
        self::ensureTable();
        return Database::getInstance()
            ->query('SELECT * FROM invite_tokens ORDER BY created_at DESC')
            ->fetchAll();
    }

    public static function delete(int $id): void
    {
        self::ensureTable();
        Database::getInstance()->prepare('DELETE FROM invite_tokens WHERE id = ?')->execute([$id]);
    }
}
