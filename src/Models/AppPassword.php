<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;

class AppPassword
{
    private static bool $tableChecked = false;

    public static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        Database::getInstance()->exec("
            CREATE TABLE IF NOT EXISTS `app_passwords` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `username`     VARCHAR(50) NOT NULL,
                `name`         VARCHAR(100) NOT NULL,
                `token_hash`   VARCHAR(255) NOT NULL,
                `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                KEY `idx_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        self::$tableChecked = true;
    }

    public static function forUser(string $username): array
    {
        self::ensureTable();
        $stmt = Database::getInstance()->prepare(
            'SELECT id, name, created_at, last_used_at FROM app_passwords WHERE username = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$username]);
        return $stmt->fetchAll();
    }

    /** Creates a new app password, returns the plaintext token (shown once). */
    public static function create(string $username, string $name): string
    {
        self::ensureTable();
        $token = bin2hex(random_bytes(16));
        $hash  = password_hash($token, PASSWORD_DEFAULT);
        $stmt  = Database::getInstance()->prepare(
            'INSERT INTO app_passwords (username, name, token_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([$username, $name, $hash]);
        // Return formatted as XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX
        return implode('-', str_split($token, 4));
    }

    /** Deletes an app password belonging to the given user. Returns true if deleted. */
    public static function delete(int $id, string $username): bool
    {
        self::ensureTable();
        $stmt = Database::getInstance()->prepare(
            'DELETE FROM app_passwords WHERE id = ? AND username = ?'
        );
        $stmt->execute([$id, $username]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Verifies a plaintext token against stored hashes for the given username.
     * Updates last_used_at on the matching record.
     */
    public static function verify(string $username, string $token): bool
    {
        self::ensureTable();
        $token = str_replace('-', '', $token);
        $pdo   = Database::getInstance();
        $stmt  = $pdo->prepare('SELECT id, token_hash FROM app_passwords WHERE username = ?');
        $stmt->execute([$username]);
        foreach ($stmt->fetchAll() as $row) {
            if (password_verify($token, $row['token_hash'])) {
                $pdo->prepare('UPDATE app_passwords SET last_used_at = NOW() WHERE id = ?')
                    ->execute([$row['id']]);
                return true;
            }
        }
        return false;
    }
}
