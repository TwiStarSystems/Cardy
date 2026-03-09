<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;

class User
{
    private static bool $roleColumnChecked = false;

    private static function ensureRoleColumn(): void
    {
        if (self::$roleColumnChecked) {
            return;
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $exists = $stmt && $stmt->fetch();

        if (!$exists) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(16) NOT NULL DEFAULT 'user' AFTER is_admin");
            $pdo->exec("UPDATE users SET role = CASE WHEN is_admin = 1 THEN 'admin' ELSE 'user' END WHERE role IS NULL OR role = ''");
        }

        self::$roleColumnChecked = true;
    }

    private static function normalizeUserRow(array $row): array
    {
        if (empty($row['role'])) {
            $row['role'] = !empty($row['is_admin']) ? 'admin' : 'user';
        }
        $row['is_admin'] = ($row['role'] === 'admin') ? 1 : 0;
        return $row;
    }

    // -------------------------------------------------------
    // Read helpers
    // -------------------------------------------------------

    public static function findById(int $id): ?array
    {
        self::ensureRoleColumn();
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, username, email, display_name, role, is_admin, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: null;
        return $row ? self::normalizeUserRow($row) : null;
    }

    public static function findByUsername(string $username): ?array
    {
        self::ensureRoleColumn();
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, username, email, display_name, role, is_admin, created_at FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch() ?: null;
        return $row ? self::normalizeUserRow($row) : null;
    }

    public static function all(): array
    {
        self::ensureRoleColumn();
        $pdo  = Database::getInstance();
        $stmt = $pdo->query('SELECT id, username, email, display_name, role, is_admin, created_at FROM users ORDER BY username');
        return array_map([self::class, 'normalizeUserRow'], $stmt->fetchAll());
    }

    // -------------------------------------------------------
    // Auth
    // -------------------------------------------------------

    public static function authenticate(string $username, string $password): ?array
    {
        self::ensureRoleColumn();
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, email, display_name, role, is_admin FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        unset($row['password_hash']);
        return self::normalizeUserRow($row);
    }

    // -------------------------------------------------------
    // Write helpers
    // -------------------------------------------------------

    /**
     * Create a new user and set up their DAV principal, address book and calendar.
     */
    public static function create(
        string $username,
        string $password,
        string $email = '',
        string $displayName = '',
        string $role = 'user'
    ): int {
        self::ensureRoleColumn();
        $pdo  = Database::getInstance();
        $role = strtolower(trim($role)) === 'admin' ? 'admin' : 'user';
        $isAdmin = $role === 'admin';

        // 1. Insert user
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, email, display_name, is_admin, role) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $hash, $email, $displayName ?: $username, $isAdmin ? 1 : 0, $role]);
        $userId = (int) $pdo->lastInsertId();

        // 2. Create SabreDAV principal
        $principalUri = "principals/{$username}";
        $pdo->prepare('INSERT INTO principals (uri, email, displayname) VALUES (?, ?, ?)')
            ->execute([$principalUri, $email, $displayName ?: $username]);

        // 3. Create default address book
        $pdo->prepare(
            'INSERT INTO addressbooks (principaluri, displayname, uri, description, synctoken) VALUES (?, ?, ?, ?, 1)'
        )->execute([$principalUri, 'My Contacts', 'default', 'Default address book']);

        // 4. Create default calendar (base calendar + instance)
        $pdo->prepare('INSERT INTO calendars (synctoken, components) VALUES (1, ?)')
            ->execute(['VEVENT,VTODO,VJOURNAL']);
        $calendarId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO calendarinstances (calendarid, principaluri, access, displayname, uri, description, calendarcolor, timezone)
             VALUES (?, ?, 1, ?, ?, ?, ?, ?)'
        )->execute([
            $calendarId,
            $principalUri,
            'My Calendar',
            'default',
            'Default calendar',
            '#9600E1',
            'UTC',
        ]);

        return $userId;
    }

    public static function update(
        int    $id,
        string $email,
        string $displayName,
        string $role
    ): void {
        self::ensureRoleColumn();
        $pdo  = Database::getInstance();
        $user = self::findById($id);
        if (!$user) {
            return;
        }

        $role = strtolower(trim($role)) === 'admin' ? 'admin' : 'user';
        $isAdmin = $role === 'admin';

        $pdo->prepare('UPDATE users SET email = ?, display_name = ?, is_admin = ?, role = ? WHERE id = ?')
            ->execute([$email, $displayName, $isAdmin ? 1 : 0, $role, $id]);

        // Sync principal
        $principalUri = "principals/{$user['username']}";
        $pdo->prepare('UPDATE principals SET email = ?, displayname = ? WHERE uri = ?')
            ->execute([$email, $displayName, $principalUri]);
    }

    public static function updatePassword(int $id, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::getInstance()
            ->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$hash, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo  = Database::getInstance();
        $user = self::findById($id);
        if (!$user) {
            return;
        }

        // Remove principal (cascade removes address-books and calendars via DB)
        $principalUri = "principals/{$user['username']}";
        $pdo->prepare('DELETE FROM principals WHERE uri = ?')->execute([$principalUri]);
        $pdo->prepare('DELETE FROM addressbooks WHERE principaluri = ?')->execute([$principalUri]);

        // Remove calendar instances and orphan calendars
        $stmt = $pdo->prepare('SELECT calendarid FROM calendarinstances WHERE principaluri = ?');
        $stmt->execute([$principalUri]);
        foreach ($stmt->fetchAll() as $row) {
            $pdo->prepare('DELETE FROM calendars WHERE id = ?')->execute([$row['calendarid']]);
        }

        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public static function count(): int
    {
        $stmt = Database::getInstance()->query('SELECT COUNT(*) FROM users');
        return (int) $stmt->fetchColumn();
    }
}
