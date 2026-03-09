<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;

class User
{
    // -------------------------------------------------------
    // Read helpers
    // -------------------------------------------------------

    public static function findById(int $id): ?array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, username, email, display_name, is_admin, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, username, email, display_name, is_admin, created_at FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public static function all(): array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->query('SELECT id, username, email, display_name, is_admin, created_at FROM users ORDER BY username');
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------
    // Auth
    // -------------------------------------------------------

    public static function authenticate(string $username, string $password): ?array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, email, display_name, is_admin FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        unset($row['password_hash']);
        return $row;
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
        bool   $isAdmin = false
    ): int {
        $pdo  = Database::getInstance();

        // 1. Insert user
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, email, display_name, is_admin) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $hash, $email, $displayName ?: $username, $isAdmin ? 1 : 0]);
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
        bool   $isAdmin
    ): void {
        $pdo  = Database::getInstance();
        $user = self::findById($id);
        if (!$user) {
            return;
        }

        $pdo->prepare('UPDATE users SET email = ?, display_name = ?, is_admin = ? WHERE id = ?')
            ->execute([$email, $displayName, $isAdmin ? 1 : 0, $id]);

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
