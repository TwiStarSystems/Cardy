<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;

class PasswordReset
{
    private static bool $tableChecked = false;

    private static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        Database::getInstance()->exec(
            "CREATE TABLE IF NOT EXISTS `password_resets` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `username`   VARCHAR(50)  NOT NULL,
                `token_hash` VARCHAR(64)  NOT NULL,
                `expires_at` TIMESTAMP    NOT NULL,
                `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `idx_token`    (`token_hash`),
                KEY        `idx_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$tableChecked = true;
    }

    /**
     * Create a reset token for the given username, returning the raw token string.
     * Any previous tokens for this user are deleted first.
     */
    public static function create(string $username): string
    {
        self::ensureTable();
        $pdo = Database::getInstance();
        self::purgeExpired($pdo);

        $pdo->prepare('DELETE FROM password_resets WHERE username = ?')->execute([$username]);

        $token   = bin2hex(random_bytes(32));
        $hash    = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $pdo->prepare('INSERT INTO password_resets (username, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([$username, $hash, $expires]);

        return $token;
    }

    /**
     * Verify a raw token. Returns the username it belongs to, or null if invalid/expired.
     */
    public static function verify(string $token): ?string
    {
        self::ensureTable();
        $hash = hash('sha256', $token);
        $stmt = Database::getInstance()->prepare(
            'SELECT username FROM password_resets WHERE token_hash = ? AND expires_at > NOW()'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row ? $row['username'] : null;
    }

    /** Consume (delete) a token after it has been used. */
    public static function consume(string $token): void
    {
        self::ensureTable();
        $hash = hash('sha256', $token);
        Database::getInstance()
            ->prepare('DELETE FROM password_resets WHERE token_hash = ?')
            ->execute([$hash]);
    }

    private static function purgeExpired(\PDO $pdo): void
    {
        $pdo->exec('DELETE FROM password_resets WHERE expires_at <= NOW()');
    }
}
