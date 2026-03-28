<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

/**
 * Contact model — wraps the SabreDAV `cards` + `addressbooks` tables
 * and uses the sabre/vobject library for vCard serialisation.
 */
class Contact
{
    private static bool $localIdChecked    = false;
    private static bool $groupTablesChecked = false;
    private static bool $historyTableChecked = false;

    private static function ensureLocalIdColumn(): void
    {
        if (self::$localIdChecked) {
            return;
        }

        $pdo = Database::getInstance();

        $columnExists = false;
        $col = $pdo->query("SHOW COLUMNS FROM cards LIKE 'local_id'");
        if ($col && $col->fetch()) {
            $columnExists = true;
        }

        if (!$columnExists) {
            $pdo->exec('ALTER TABLE cards ADD COLUMN local_id INT UNSIGNED NULL AFTER addressbookid');
        }

        $indexExists = false;
        $idx = $pdo->query("SHOW INDEX FROM cards WHERE Key_name = 'idx_addressbook_local_id'");
        if ($idx && $idx->fetch()) {
            $indexExists = true;
        }

        if (!$indexExists) {
            $pdo->exec('CREATE UNIQUE INDEX idx_addressbook_local_id ON cards (addressbookid, local_id)');
        }

        $stmt = $pdo->query('SELECT id, addressbookid FROM cards WHERE local_id IS NULL ORDER BY addressbookid, id');
        foreach ($stmt->fetchAll() as $row) {
            $next = self::nextFreeLocalId((int) $row['addressbookid']);
            $pdo->prepare('UPDATE cards SET local_id = ? WHERE id = ?')->execute([$next, (int) $row['id']]);
        }

        self::$localIdChecked = true;
    }

    private static function nextFreeLocalId(int $addressBookId): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT local_id FROM cards WHERE addressbookid = ? AND local_id IS NOT NULL ORDER BY local_id ASC');
        $stmt->execute([$addressBookId]);

        $expected = 1;
        foreach ($stmt->fetchAll() as $row) {
            $current = (int) $row['local_id'];
            if ($current > $expected) {
                break;
            }
            if ($current === $expected) {
                $expected++;
            }
        }

        return $expected;
    }
    // -------------------------------------------------------
    // Address-book helpers
    // -------------------------------------------------------

    public static function getAddressBookId(string $username, ?int $explicit = null): ?int
    {
        if ($explicit !== null) {
            return $explicit;
        }
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT id FROM addressbooks WHERE principaluri = ? ORDER BY id LIMIT 1"
        );
        $stmt->execute(["principals/{$username}"]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    // -------------------------------------------------------
    // Address-book management
    // -------------------------------------------------------

    /**
     * Converts a display name to a URL-safe slug (lowercase alphanumeric + hyphens).
     */
    public static function slugify(string $input): string
    {
        $slug = strtolower(trim($input));
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'book';
    }

    private static function uniqueAddressBookSlug(string $username, string $baseSlug): string
    {
        $pdo  = Database::getInstance();
        $slug = $baseSlug;
        $i    = 2;
        while (true) {
            $stmt = $pdo->prepare('SELECT id FROM addressbooks WHERE principaluri = ? AND uri = ?');
            $stmt->execute(["principals/{$username}", $slug]);
            if (!$stmt->fetch()) {
                break;
            }
            $slug = $baseSlug . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Returns all address books for a user with their contact count.
     */
    public static function getAllAddressBooksForUser(string $username): array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT ab.id, ab.uri, ab.displayname,
                    (SELECT COUNT(*) FROM cards c WHERE c.addressbookid = ab.id) AS card_count
             FROM addressbooks ab
             WHERE ab.principaluri = ?
             ORDER BY ab.id ASC'
        );
        $stmt->execute(["principals/{$username}"]);
        return $stmt->fetchAll();
    }

    /**
     * Create a new address book. The URL slug is generated from the display name
     * and is immutable after creation. Returns the new book data.
     */
    public static function createAddressBook(string $username, string $displayName): array
    {
        $pdo         = Database::getInstance();
        $displayName = trim($displayName) ?: 'New Address Book';
        $baseSlug    = self::slugify($displayName);
        $slug        = self::uniqueAddressBookSlug($username, $baseSlug);
        $pdo->prepare(
            'INSERT INTO addressbooks (principaluri, displayname, uri, description, synctoken) VALUES (?, ?, ?, ?, 1)'
        )->execute(["principals/{$username}", $displayName, $slug, '']);
        return [
            'id'          => (int) $pdo->lastInsertId(),
            'uri'         => $slug,
            'displayname' => $displayName,
        ];
    }

    /**
     * Rename the display name of an address book. The URI/slug is never changed.
     */
    public static function renameAddressBook(int $id, string $username, string $newName): void
    {
        $pdo     = Database::getInstance();
        $newName = trim($newName) ?: 'Address Book';
        $pdo->prepare(
            'UPDATE addressbooks SET displayname = ?, synctoken = synctoken + 1 WHERE id = ? AND principaluri = ?'
        )->execute([$newName, $id, "principals/{$username}"]);
    }

    /**
     * Delete an address book and all its contacts. Throws if it's the user's last one.
     */
    public static function deleteAddressBook(int $id, string $username): void
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM addressbooks WHERE principaluri = ?');
        $stmt->execute(["principals/{$username}"]);
        if ((int) $stmt->fetchColumn() <= 1) {
            throw new \RuntimeException('Cannot delete the only address book.');
        }
        $stmt = $pdo->prepare('SELECT id FROM addressbooks WHERE id = ? AND principaluri = ?');
        $stmt->execute([$id, "principals/{$username}"]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Address book not found.');
        }
        // ON DELETE CASCADE cleans up cards, contact_groups, contact_group_members
        $pdo->prepare('DELETE FROM addressbooks WHERE id = ?')->execute([$id]);
    }

    // -------------------------------------------------------
    // List / fetch
    // -------------------------------------------------------

    public static function allForUser(
        string $username,
        string $search        = '',
        string $sort          = 'default',
        string $groupFilter   = '',
        bool   $starredOnly   = false,
        ?int   $addressBookId = null
    ): array {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT id, local_id, uri, carddata, lastmodified FROM cards WHERE addressbookid = ? ORDER BY local_id ASC, lastmodified DESC'
        );
        $stmt->execute([$abId]);
        $rows = $stmt->fetchAll();

        $contacts = [];
        foreach ($rows as $row) {
            $parsed = self::parseVCard($row['carddata']);
            if ($search && stripos($parsed['fn'] . ' ' . $parsed['email'] . ' ' . $parsed['org'], $search) === false) {
                continue;
            }
            $contacts[] = array_merge([
                'id'           => (int) ($row['local_id'] ?: $row['id']),
                'db_id'        => (int) $row['id'],
                'uri'          => $row['uri'],
                'lastmodified' => (int) $row['lastmodified'],
            ], $parsed);
        }

        // Load groups for all contacts in one batch query
        self::ensureGroupTables();
        if (!empty($contacts)) {
            $dbIds        = array_column($contacts, 'db_id');
            $placeholders = implode(',', array_fill(0, count($dbIds), '?'));
            $stmt2 = $pdo->prepare(
                "SELECT cgm.card_id, cg.id AS group_id, cg.name, cg.color
                 FROM contact_group_members cgm
                 JOIN contact_groups cg ON cg.id = cgm.group_id
                 WHERE cgm.card_id IN ($placeholders)"
            );
            $stmt2->execute($dbIds);
            $groupsByCard = [];
            foreach ($stmt2->fetchAll() as $gRow) {
                $groupsByCard[(int) $gRow['card_id']][] = [
                    'id'    => (int) $gRow['group_id'],
                    'name'  => $gRow['name'],
                    'color' => $gRow['color'],
                ];
            }
            foreach ($contacts as &$c) {
                $c['groups'] = $groupsByCard[$c['db_id']] ?? [];
            }
            unset($c);
        }

        // Apply starred / group filters
        if ($starredOnly) {
            $contacts = array_values(array_filter(
                $contacts,
                static fn(array $c) => !empty($c['is_starred'])
            ));
        }
        if ($groupFilter !== '') {
            $gid      = (int) $groupFilter;
            $contacts = array_values(array_filter(
                $contacts,
                static function (array $c) use ($gid): bool {
                    foreach ($c['groups'] as $g) {
                        if ((int) $g['id'] === $gid) {
                            return true;
                        }
                    }
                    return false;
                }
            ));
        }

        return self::sortContacts($contacts, $sort);
    }

    private static function sortContacts(array $contacts, string $sort): array
    {
        if ($sort === 'default') {
            return $contacts;
        }

        usort($contacts, static function (array $a, array $b) use ($sort): int {
            switch ($sort) {
                case 'first_name':
                    $cmp = strcasecmp(trim((string) ($a['first_name'] ?? '')), trim((string) ($b['first_name'] ?? '')));
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    $cmp = strcasecmp(trim((string) ($a['last_name'] ?? '')), trim((string) ($b['last_name'] ?? '')));
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    return strcasecmp(trim((string) ($a['fn'] ?? '')), trim((string) ($b['fn'] ?? '')));

                case 'last_name':
                    $cmp = strcasecmp(trim((string) ($a['last_name'] ?? '')), trim((string) ($b['last_name'] ?? '')));
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    $cmp = strcasecmp(trim((string) ($a['first_name'] ?? '')), trim((string) ($b['first_name'] ?? '')));
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    return strcasecmp(trim((string) ($a['fn'] ?? '')), trim((string) ($b['fn'] ?? '')));

                case 'birthday':
                    $aBirthday = trim((string) ($a['birthday'] ?? ''));
                    $bBirthday = trim((string) ($b['birthday'] ?? ''));
                    if ($aBirthday === '' && $bBirthday !== '') {
                        return 1;
                    }
                    if ($aBirthday !== '' && $bBirthday === '') {
                        return -1;
                    }
                    $cmp = strcasecmp($aBirthday, $bBirthday);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    return strcasecmp(trim((string) ($a['fn'] ?? '')), trim((string) ($b['fn'] ?? '')));

                case 'organization':
                    $cmp = strcasecmp(trim((string) ($a['org'] ?? '')), trim((string) ($b['org'] ?? '')));
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    return strcasecmp(trim((string) ($a['fn'] ?? '')), trim((string) ($b['fn'] ?? '')));

                case 'recently_updated':
                    $cmp = ((int) ($b['lastmodified'] ?? 0)) <=> ((int) ($a['lastmodified'] ?? 0));
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));

                default:
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
        });

        return $contacts;
    }

    public static function findById(int $id, string $username, ?int $addressBookId = null): ?array
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM cards WHERE addressbookid = ? AND (local_id = ? OR id = ?) ORDER BY CASE WHEN local_id = ? THEN 0 ELSE 1 END, id LIMIT 1'
        );
        $stmt->execute([$abId, $id, $id, $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $parsed = self::parseVCard($row['carddata']);
        self::ensureGroupTables();
        $parsed['groups'] = self::getContactGroups((int) $row['id']);

        return array_merge([
            'id'    => (int) ($row['local_id'] ?: $row['id']),
            'db_id' => (int) $row['id'],
            'uri'   => $row['uri'],
        ], $parsed);
    }

    // -------------------------------------------------------
    // Parse / build
    // -------------------------------------------------------

    public static function parseVCard(string $data): array
    {
        try {
            $vcard = Reader::read($data);
        } catch (\Exception $e) {
            return self::emptyFields();
        }

        $phones = [];
        if (isset($vcard->TEL)) {
            foreach ($vcard->TEL as $tel) {
                $type = '';
                if ($tel['TYPE']) {
                    $type = strtolower((string) $tel['TYPE']);
                }
                $phones[] = ['type' => $type, 'number' => (string) $tel];
            }
        }

        $emails = [];
        if (isset($vcard->EMAIL)) {
            foreach ($vcard->EMAIL as $email) {
                $type = '';
                if ($email['TYPE']) {
                    $type = strtolower((string) $email['TYPE']);
                }
                $emails[] = ['type' => $type, 'address' => (string) $email];
            }
        }

        $addresses = [];
        if (isset($vcard->ADR)) {
            foreach ($vcard->ADR as $adr) {
                $type = '';
                if ($adr['TYPE']) {
                    $type = strtolower((string) $adr['TYPE']);
                }
                $parts = $adr->getParts();
                $addresses[] = [
                    'type'     => $type,
                    'street'   => $parts[2] ?? '',
                    'city'     => $parts[3] ?? '',
                    'region'   => $parts[4] ?? '',
                    'postcode' => $parts[5] ?? '',
                    'country'  => $parts[6] ?? '',
                ];
            }
        }

        $photo = '';
        $photoMime = 'image/jpeg';
        if (isset($vcard->PHOTO)) {
            $photoProperty = $vcard->PHOTO[0] ?? $vcard->PHOTO;
            $rawPhoto = (string) $photoProperty;
            $normalized = preg_replace('/\s+/', '', $rawPhoto) ?? '';

            if ($normalized !== '' && base64_decode($normalized, true) !== false) {
                $photo = $normalized;
            } elseif ($rawPhoto !== '') {
                $photo = base64_encode($rawPhoto);
            }

            $typeParam = strtoupper(trim((string) ($photoProperty['TYPE'] ?? '')));
            if (in_array($typeParam, ['PNG', 'GIF', 'WEBP', 'BMP', 'JPEG', 'JPG'], true)) {
                $photoMime = $typeParam === 'JPG' ? 'image/jpeg' : 'image/' . strtolower($typeParam);
            }
        }

        $nameParts = ['', '', '', '', ''];
        if (isset($vcard->N)) {
            $nameParts = $vcard->N->getParts();
            while (count($nameParts) < 5) {
                $nameParts[] = '';
            }
        }

        // URLs
        $urls = [];
        if (isset($vcard->URL)) {
            foreach ($vcard->URL as $url) {
                $type = $url['TYPE'] ? strtolower((string) $url['TYPE']) : '';
                $urls[] = ['type' => $type, 'value' => (string) $url];
            }
        }

        // Social profiles — X-SOCIALPROFILE (Apple/DAVx5) + per-network X- props
        $socialProfiles = [];
        if (isset($vcard->{'X-SOCIALPROFILE'})) {
            foreach ($vcard->{'X-SOCIALPROFILE'} as $sp) {
                $type = $sp['TYPE'] ? strtolower((string) $sp['TYPE']) : 'other';
                $socialProfiles[] = ['type' => $type, 'value' => (string) $sp];
            }
        }
        foreach (['twitter', 'linkedin', 'instagram', 'facebook', 'github', 'youtube', 'mastodon'] as $net) {
            $key = 'X-' . strtoupper($net);
            if (isset($vcard->{$key})) {
                foreach ($vcard->{$key} as $sp) {
                    $socialProfiles[] = ['type' => $net, 'value' => (string) $sp];
                }
            }
        }

        // Nickname
        $nickname = isset($vcard->NICKNAME) ? (string) $vcard->NICKNAME : '';

        // Anniversaries
        $anniversaries = [];
        foreach (['ANNIVERSARY', 'X-ANNIVERSARY', 'X-ABDATE'] as $propName) {
            if (isset($vcard->{$propName})) {
                foreach ($vcard->{$propName} as $a) {
                    $anniversaries[] = (string) $a;
                }
            }
        }

        // Custom fields (X-CARDY-CUSTOM;LABEL=xxx:value)
        $customFields = [];
        if (isset($vcard->{'X-CARDY-CUSTOM'})) {
            foreach ($vcard->{'X-CARDY-CUSTOM'} as $cf) {
                $label = $cf['LABEL'] ? (string) $cf['LABEL'] : 'Custom';
                $customFields[] = ['label' => $label, 'value' => (string) $cf];
            }
        }

        // Related contacts (X-ABRELATEDNAMES Apple-style + RELATED vCard4 extension)
        $related = [];
        if (isset($vcard->{'X-ABRELATEDNAMES'})) {
            foreach ($vcard->{'X-ABRELATEDNAMES'} as $r) {
                $type = $r['X-ABLabel'] ? strtolower((string) $r['X-ABLabel']) : 'other';
                $related[] = ['name' => (string) $r, 'type' => $type];
            }
        }
        if (isset($vcard->RELATED)) {
            foreach ($vcard->RELATED as $r) {
                $type = $r['TYPE'] ? strtolower((string) $r['TYPE']) : 'other';
                $related[] = ['name' => (string) $r, 'type' => $type];
            }
        }

        // Starred flag
        $isStarred = isset($vcard->{'X-CARDY-STARRED'}) && (string) $vcard->{'X-CARDY-STARRED'} === '1';

        // Ignore-duplicate flag
        $ignoreDuplicate = isset($vcard->{'X-CARDY-NO-DUPLICATE'}) && (string) $vcard->{'X-CARDY-NO-DUPLICATE'} === '1';

        return [
            'fn'             => isset($vcard->FN)  ? (string) $vcard->FN  : '',
            'last_name'      => $nameParts[0],
            'first_name'     => $nameParts[1],
            'org'            => isset($vcard->ORG)  ? (string) $vcard->ORG  : '',
            'title'          => isset($vcard->TITLE) ? (string) $vcard->TITLE : '',
            'nickname'       => $nickname,
            'email'          => !empty($emails) ? $emails[0]['address'] : '',
            'emails'         => $emails,
            'phone'          => !empty($phones) ? $phones[0]['number'] : '',
            'phones'         => $phones,
            'addresses'      => $addresses,
            'urls'           => $urls,
            'social_profiles' => $socialProfiles,
            'anniversaries'  => $anniversaries,
            'custom_fields'  => $customFields,
            'related'        => $related,
            'birthday'         => isset($vcard->BDAY)  ? (string) $vcard->BDAY  : '',
            'note'             => isset($vcard->NOTE)  ? (string) $vcard->NOTE  : '',
            'photo'            => $photo,
            'photo_mime'       => $photoMime,
            'uid'              => isset($vcard->UID)   ? (string) $vcard->UID   : '',
            'is_starred'       => $isStarred,
            'ignore_duplicate' => $ignoreDuplicate,
            'groups'           => [],  // populated by allForUser() / findById()
        ];
    }

    private static function emptyFields(): array
    {
        return [
            'fn' => '', 'last_name' => '', 'first_name' => '', 'org' => '',
            'title' => '', 'nickname' => '', 'email' => '', 'emails' => [],
            'phone' => '', 'phones' => [], 'addresses' => [],
            'urls' => [], 'social_profiles' => [], 'anniversaries' => [],
            'custom_fields' => [], 'related' => [], 'birthday' => '', 'note' => '',
            'photo' => '', 'photo_mime' => 'image/jpeg', 'uid' => '',
            'is_starred' => false, 'ignore_duplicate' => false, 'groups' => [],
        ];
    }

    public static function buildVCard(array $data): string
    {
        $uid = $data['uid'] ?: 'cardy-' . bin2hex(random_bytes(16));

        $vcard = new VCard([
            'VERSION' => '3.0',
            'UID'     => $uid,
        ]);

        $fn = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        if (empty($fn)) {
            $fn = $data['org'] ?: $data['email'] ?: 'Unknown';
        }
        $vcard->add('FN', $fn);
        $vcard->add('N', [
            $data['last_name']  ?? '',
            $data['first_name'] ?? '',
            '',
            '',
            '',
        ]);

        if (!empty($data['org'])) {
            $vcard->add('ORG', $data['org']);
        }
        if (!empty($data['title'])) {
            $vcard->add('TITLE', $data['title']);
        }

        // Emails
        foreach (($data['emails'] ?? []) as $e) {
            if (empty($e['address'])) {
                continue;
            }
            $prop = $vcard->add('EMAIL', $e['address']);
            if (!empty($e['type'])) {
                $prop->add('TYPE', strtoupper($e['type']));
            }
        }

        // Phones
        foreach (($data['phones'] ?? []) as $p) {
            if (empty($p['number'])) {
                continue;
            }
            $prop = $vcard->add('TEL', $p['number']);
            if (!empty($p['type'])) {
                $prop->add('TYPE', strtoupper($p['type']));
            }
        }

        // Addresses
        foreach (($data['addresses'] ?? []) as $a) {
            $adrParams = [];
            if (!empty($a['type'])) {
                $adrParams['TYPE'] = strtoupper($a['type']);
            }
            $vcard->add('ADR', [
                '',
                '',
                $a['street']   ?? '',
                $a['city']     ?? '',
                $a['region']   ?? '',
                $a['postcode'] ?? '',
                $a['country']  ?? '',
            ], $adrParams);
        }

        if (!empty($data['birthday'])) {
            $vcard->add('BDAY', $data['birthday']);
        }
        if (!empty($data['note'])) {
            $vcard->add('NOTE', $data['note']);
        }
        if (!empty($data['nickname'])) {
            $vcard->add('NICKNAME', $data['nickname']);
        }

        // URLs
        foreach (($data['urls'] ?? []) as $u) {
            if (empty($u['value'])) {
                continue;
            }
            $urlParams = [];
            if (!empty($u['type'])) {
                $urlParams['TYPE'] = strtoupper($u['type']);
            }
            $vcard->add('URL', $u['value'], $urlParams);
        }

        // Social profiles
        foreach (($data['social_profiles'] ?? []) as $sp) {
            if (empty($sp['value'])) {
                continue;
            }
            $type = strtolower($sp['type'] ?? 'other');
            $vcard->add('X-SOCIALPROFILE', $sp['value'], ['TYPE' => $type]);
        }

        // Anniversaries
        foreach (($data['anniversaries'] ?? []) as $ann) {
            if (empty($ann)) {
                continue;
            }
            $vcard->add('X-ANNIVERSARY', $ann);
        }

        // Custom fields
        foreach (($data['custom_fields'] ?? []) as $cf) {
            if (empty($cf['value'])) {
                continue;
            }
            $label = preg_replace('/[^A-Za-z0-9 _-]/', '', $cf['label'] ?? 'Custom') ?: 'Custom';
            $vcard->add('X-CARDY-CUSTOM', $cf['value'], ['LABEL' => $label]);
        }

        // Related contacts
        foreach (($data['related'] ?? []) as $rel) {
            if (empty($rel['name'])) {
                continue;
            }
            $vcard->add('X-ABRELATEDNAMES', $rel['name'], ['X-ABLabel' => $rel['type'] ?? 'other']);
        }

        if (!empty($data['is_starred'])) {
            $vcard->add('X-CARDY-STARRED', '1');
        }
        if (!empty($data['ignore_duplicate'])) {
            $vcard->add('X-CARDY-NO-DUPLICATE', '1');
        }

        self::applyPhotoManagedFields($vcard, $data);

        return $vcard->serialize();
    }

    private static function applyManagedFields(VCard $vcard, array $data): void
    {
        $uid = $data['uid'] ?: 'cardy-' . bin2hex(random_bytes(16));

        unset($vcard->FN);
        unset($vcard->N);
        unset($vcard->ORG);
        unset($vcard->TITLE);
        unset($vcard->NICKNAME);
        unset($vcard->EMAIL);
        unset($vcard->TEL);
        unset($vcard->ADR);
        unset($vcard->URL);
        unset($vcard->{'X-SOCIALPROFILE'});
        unset($vcard->{'X-ANNIVERSARY'});
        unset($vcard->{'X-CARDY-CUSTOM'});
        unset($vcard->BDAY);
        unset($vcard->NOTE);
        unset($vcard->UID);
        unset($vcard->{'X-CARDY-STARRED'});
        unset($vcard->{'X-CARDY-NO-DUPLICATE'});
        unset($vcard->{'X-ABRELATEDNAMES'});

        $vcard->add('UID', $uid);

        $fn = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        if (empty($fn)) {
            $fn = $data['org'] ?: $data['email'] ?: 'Unknown';
        }
        $vcard->add('FN', $fn);
        $vcard->add('N', [
            $data['last_name']  ?? '',
            $data['first_name'] ?? '',
            '',
            '',
            '',
        ]);

        if (!empty($data['org'])) {
            $vcard->add('ORG', $data['org']);
        }
        if (!empty($data['title'])) {
            $vcard->add('TITLE', $data['title']);
        }

        foreach (($data['emails'] ?? []) as $e) {
            if (empty($e['address'])) {
                continue;
            }
            $prop = $vcard->add('EMAIL', $e['address']);
            if (!empty($e['type'])) {
                $prop->add('TYPE', strtoupper($e['type']));
            }
        }

        foreach (($data['phones'] ?? []) as $p) {
            if (empty($p['number'])) {
                continue;
            }
            $prop = $vcard->add('TEL', $p['number']);
            if (!empty($p['type'])) {
                $prop->add('TYPE', strtoupper($p['type']));
            }
        }

        foreach (($data['addresses'] ?? []) as $a) {
            $adrParams = [];
            if (!empty($a['type'])) {
                $adrParams['TYPE'] = strtoupper($a['type']);
            }
            $vcard->add('ADR', [
                '',
                '',
                $a['street']   ?? '',
                $a['city']     ?? '',
                $a['region']   ?? '',
                $a['postcode'] ?? '',
                $a['country']  ?? '',
            ], $adrParams);
        }

        if (!empty($data['birthday'])) {
            $vcard->add('BDAY', $data['birthday']);
        }
        if (!empty($data['note'])) {
            $vcard->add('NOTE', $data['note']);
        }
        if (!empty($data['nickname'])) {
            $vcard->add('NICKNAME', $data['nickname']);
        }

        // URLs
        foreach (($data['urls'] ?? []) as $u) {
            if (empty($u['value'])) {
                continue;
            }
            $urlParams = [];
            if (!empty($u['type'])) {
                $urlParams['TYPE'] = strtoupper($u['type']);
            }
            $vcard->add('URL', $u['value'], $urlParams);
        }

        // Social profiles
        foreach (($data['social_profiles'] ?? []) as $sp) {
            if (empty($sp['value'])) {
                continue;
            }
            $type = strtolower($sp['type'] ?? 'other');
            $vcard->add('X-SOCIALPROFILE', $sp['value'], ['TYPE' => $type]);
        }

        // Anniversaries
        foreach (($data['anniversaries'] ?? []) as $ann) {
            if (empty($ann)) {
                continue;
            }
            $vcard->add('X-ANNIVERSARY', $ann);
        }

        // Custom fields
        foreach (($data['custom_fields'] ?? []) as $cf) {
            if (empty($cf['value'])) {
                continue;
            }
            $label = preg_replace('/[^A-Za-z0-9 _-]/', '', $cf['label'] ?? 'Custom') ?: 'Custom';
            $vcard->add('X-CARDY-CUSTOM', $cf['value'], ['LABEL' => $label]);
        }

        // Related contacts
        foreach (($data['related'] ?? []) as $rel) {
            if (empty($rel['name'])) {
                continue;
            }
            $vcard->add('X-ABRELATEDNAMES', $rel['name'], ['X-ABLabel' => $rel['type'] ?? 'other']);
        }

        if (!empty($data['is_starred'])) {
            $vcard->add('X-CARDY-STARRED', '1');
        }
        if (!empty($data['ignore_duplicate'])) {
            $vcard->add('X-CARDY-NO-DUPLICATE', '1');
        }

        self::applyPhotoManagedFields($vcard, $data);
    }

    private static function applyPhotoManagedFields(VCard $vcard, array $data): void
    {
        if (!empty($data['remove_photo'])) {
            unset($vcard->PHOTO);
        }

        if (empty($data['photo_upload']) || !is_array($data['photo_upload'])) {
            return;
        }

        $photoBinary = (string) ($data['photo_upload']['data'] ?? '');
        if ($photoBinary === '') {
            return;
        }

        $vcardType = strtoupper((string) ($data['photo_upload']['vcard_type'] ?? 'JPEG'));
        if ($vcardType === 'JPG') {
            $vcardType = 'JPEG';
        }

        unset($vcard->PHOTO);
        $vcard->add('PHOTO', base64_encode($photoBinary), [
            'ENCODING' => 'b',
            'TYPE'     => $vcardType,
        ]);
    }

    // -------------------------------------------------------
    // Write
    // -------------------------------------------------------

    private static function ensureUniqueUri(int $addressBookId, string $baseUri): string
    {
        $pdo = Database::getInstance();

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', $baseUri) ?: ('cardy-' . bin2hex(random_bytes(8)) . '.vcf');
        $safeBase = preg_replace('/-+/', '-', $safeBase) ?: ('cardy-' . bin2hex(random_bytes(8)) . '.vcf');

        $candidate = $safeBase;
        $suffix = 1;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cards WHERE addressbookid = ? AND uri = ?');
        while (true) {
            $stmt->execute([$addressBookId, $candidate]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $candidate;
            }

            $suffix++;
            $candidate = preg_replace('/\.vcf$/i', '', $safeBase) . '-' . $suffix . '.vcf';
        }
    }

    private static function createFromSerializedVCard(int $addressBookId, string $serializedVCard): int
    {
        self::ensureLocalIdColumn();
        $pdo = Database::getInstance();

        try {
            $vcard = Reader::read($serializedVCard);
            if (!($vcard instanceof VCard)) {
                $vcard = new VCard(['VERSION' => '3.0']);
            }
        } catch (\Exception $e) {
            $vcard = new VCard(['VERSION' => '3.0']);
        }

        $uid = isset($vcard->UID) ? trim((string) $vcard->UID) : '';
        if ($uid === '') {
            $uid = 'cardy-' . bin2hex(random_bytes(16));
            $vcard->add('UID', $uid);
        }

        if (!isset($vcard->VERSION)) {
            $vcard->add('VERSION', '3.0');
        }

        $vcardData = $vcard->serialize();
        $uri = self::ensureUniqueUri($addressBookId, $uid . '.vcf');
        $localId = self::nextFreeLocalId($addressBookId);
        $etag = md5($vcardData);
        $now = time();

        $pdo->prepare(
            'INSERT INTO cards (addressbookid, local_id, carddata, uri, lastmodified, etag, size) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$addressBookId, $localId, $vcardData, $uri, $now, $etag, strlen($vcardData)]);

        $cardId = (int) $pdo->lastInsertId();
        self::bumpSyncToken($addressBookId, $uri, 1);

        return $cardId;
    }

    public static function importVCardData(string $username, string $rawData, ?int $addressBookId = null): array
    {
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            throw new \RuntimeException('No address book found for user.');
        }

        preg_match_all('/BEGIN:VCARD[\s\S]*?END:VCARD\s*/i', $rawData, $matches);
        $entries = $matches[0] ?? [];

        if (empty($entries) && stripos($rawData, 'BEGIN:VCARD') !== false) {
            $entries = [trim($rawData)];
        }

        if (empty($entries)) {
            throw new \RuntimeException('No vCard entries found in file.');
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($entries as $index => $entry) {
            try {
                self::createFromSerializedVCard($abId, trim($entry));
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'vCard #' . ($index + 1) . ': ' . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'failed'   => $failed,
            'errors'   => $errors,
        ];
    }

    public static function create(string $username, array $data, ?int $addressBookId = null): int
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            throw new \RuntimeException('No address book found for user.');
        }

        $uid      = 'cardy-' . bin2hex(random_bytes(16));
        $data['uid'] = $uid;
        $vcardData = self::buildVCard($data);
        $uri      = self::ensureUniqueUri($abId, $uid . '.vcf');
        $localId  = self::nextFreeLocalId($abId);
        $etag     = md5($vcardData);
        $now      = time();

        $pdo->prepare(
            'INSERT INTO cards (addressbookid, local_id, carddata, uri, lastmodified, etag, size) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$abId, $localId, $vcardData, $uri, $now, $etag, strlen($vcardData)]);

        $cardId = (int) $pdo->lastInsertId();
        self::bumpSyncToken($abId, $uri, 1);

        // Handle group assignments
        if (!empty($data['groups']) && is_array($data['groups'])) {
            self::ensureGroupTables();
            self::setContactGroups($cardId, $data['groups']);
        }

        // Record history
        self::ensureHistoryTable();
        $fn = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: ($data['org'] ?? 'Contact');
        self::recordHistory($cardId, 'created', $fn);

        return $cardId;
    }

    public static function update(int $id, string $username, array $data, ?int $addressBookId = null): void
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT id, uri, carddata FROM cards WHERE addressbookid = ? AND (local_id = ? OR id = ?) ORDER BY CASE WHEN local_id = ? THEN 0 ELSE 1 END, id LIMIT 1'
        );
        $stmt->execute([$abId, $id, $id, $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        // Preserve original UID
        $existing = self::parseVCard($row['carddata']);
        $data['uid'] = $existing['uid'] ?: ('cardy-' . bin2hex(random_bytes(16)));

        try {
            $vcard = Reader::read($row['carddata']);
            if (!($vcard instanceof VCard)) {
                $vcard = new VCard(['VERSION' => '3.0']);
            }
        } catch (\Exception $e) {
            $vcard = new VCard(['VERSION' => '3.0']);
        }

        self::applyManagedFields($vcard, $data);
        $vcardData = $vcard->serialize();
        $etag      = md5($vcardData);
        $now       = time();

        $pdo->prepare('UPDATE cards SET carddata = ?, etag = ?, size = ?, lastmodified = ? WHERE id = ?')
            ->execute([$vcardData, $etag, strlen($vcardData), $now, (int) $row['id']]);

        self::bumpSyncToken($abId, $row['uri'], 2);

        // Handle group assignments
        if (isset($data['groups']) && is_array($data['groups'])) {
            self::ensureGroupTables();
            self::setContactGroups((int) $row['id'], $data['groups']);
        }

        // Record history
        self::ensureHistoryTable();
        $fn = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: ($data['org'] ?? 'Contact');
        self::recordHistory((int) $row['id'], 'updated', $fn);
    }

    public static function delete(int $id, string $username, ?int $addressBookId = null): void
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT id, uri FROM cards WHERE addressbookid = ? AND (local_id = ? OR id = ?) ORDER BY CASE WHEN local_id = ? THEN 0 ELSE 1 END, id LIMIT 1'
        );
        $stmt->execute([$abId, $id, $id, $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        // Record history before deletion
        self::ensureHistoryTable();
        self::recordHistory((int) $row['id'], 'deleted', (string) $row['uri']);

        $pdo->prepare('DELETE FROM cards WHERE id = ?')->execute([(int) $row['id']]);
        self::bumpSyncToken($abId, $row['uri'], 3);
    }

    public static function countForUser(string $username, ?int $addressBookId = null): int
    {
        self::ensureLocalIdColumn();
        $pdo = Database::getInstance();
        if ($addressBookId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM cards WHERE addressbookid = ?');
            $stmt->execute([$addressBookId]);
        } else {
            // Count across all address books for this user
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM cards c
                 JOIN addressbooks ab ON c.addressbookid = ab.id
                 WHERE ab.principaluri = ?'
            );
            $stmt->execute(["principals/{$username}"]);
        }
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------
    // Export
    // -------------------------------------------------------

    /**
     * Export all contacts for a user as a single multi-vCard (.vcf) blob.
     */
    public static function exportAllVCards(string $username, ?int $addressBookId = null): string
    {
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return '';
        }

        $stmt = $pdo->prepare('SELECT carddata FROM cards WHERE addressbookid = ? ORDER BY local_id ASC, id ASC');
        $stmt->execute([$abId]);

        $parts = [];
        foreach ($stmt->fetchAll() as $row) {
            $parts[] = rtrim((string) $row['carddata']);
        }

        return implode("\r\n", $parts) . "\r\n";
    }

    /**
     * Export all contacts for a user as a CSV string.
     */
    public static function exportAllCsv(string $username, ?int $addressBookId = null): string
    {
        $contacts = self::allForUser($username, addressBookId: $addressBookId);

        $stream = fopen('php://temp', 'r+');

        // Header row
        fputcsv($stream, [
            'first_name', 'last_name', 'org', 'title', 'nickname', 'birthday', 'note',
            'email1', 'email1_type', 'email2', 'email2_type', 'email3', 'email3_type',
            'phone1', 'phone1_type', 'phone2', 'phone2_type', 'phone3', 'phone3_type',
            'home_street', 'home_city', 'home_region', 'home_postcode', 'home_country',
            'work_street', 'work_city', 'work_region', 'work_postcode', 'work_country',
            'url1', 'url1_type', 'url2', 'url2_type',
            'social1_type', 'social1_value', 'social2_type', 'social2_value', 'social3_type', 'social3_value',
            'anniversary1', 'anniversary2',
            'custom1_label', 'custom1_value', 'custom2_label', 'custom2_value',
        ]);

        foreach ($contacts as $c) {
            $emails = $c['emails'] ?? [];
            $phones = $c['phones'] ?? [];

            $home = ['', '', '', '', ''];
            $work = ['', '', '', '', ''];
            foreach ($c['addresses'] ?? [] as $addr) {
                $row = [
                    $addr['street']   ?? '',
                    $addr['city']     ?? '',
                    $addr['region']   ?? '',
                    $addr['postcode'] ?? '',
                    $addr['country']  ?? '',
                ];
                if (strtolower($addr['type'] ?? '') === 'work') {
                    $work = $row;
                } else {
                    $home = $row;
                }
            }

            fputcsv($stream, [
                $c['first_name'] ?? '',
                $c['last_name']  ?? '',
                $c['org']        ?? '',
                $c['title']      ?? '',
                $c['nickname']   ?? '',
                $c['birthday']   ?? '',
                $c['note']       ?? '',
                $emails[0]['address'] ?? '', $emails[0]['type'] ?? '',
                $emails[1]['address'] ?? '', $emails[1]['type'] ?? '',
                $emails[2]['address'] ?? '', $emails[2]['type'] ?? '',
                $phones[0]['number']  ?? '', $phones[0]['type'] ?? '',
                $phones[1]['number']  ?? '', $phones[1]['type'] ?? '',
                $phones[2]['number']  ?? '', $phones[2]['type'] ?? '',
                $home[0], $home[1], $home[2], $home[3], $home[4],
                $work[0], $work[1], $work[2], $work[3], $work[4],
                ($c['urls'][0]['value'] ?? ''), ($c['urls'][0]['type'] ?? ''),
                ($c['urls'][1]['value'] ?? ''), ($c['urls'][1]['type'] ?? ''),
                ($c['social_profiles'][0]['type'] ?? ''), ($c['social_profiles'][0]['value'] ?? ''),
                ($c['social_profiles'][1]['type'] ?? ''), ($c['social_profiles'][1]['value'] ?? ''),
                ($c['social_profiles'][2]['type'] ?? ''), ($c['social_profiles'][2]['value'] ?? ''),
                ($c['anniversaries'][0] ?? ''), ($c['anniversaries'][1] ?? ''),
                ($c['custom_fields'][0]['label'] ?? ''), ($c['custom_fields'][0]['value'] ?? ''),
                ($c['custom_fields'][1]['label'] ?? ''), ($c['custom_fields'][1]['value'] ?? ''),
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    // -------------------------------------------------------
    // Google / Outlook export helpers
    // -------------------------------------------------------

    private static function googleEmailLabel(string $type): string
    {
        return match (strtolower($type)) {
            'work'  => '* Work',
            'home'  => '* Home',
            'other' => '* Other',
            default => '* Main',
        };
    }

    private static function googlePhoneLabel(string $type): string
    {
        return match (strtolower($type)) {
            'cell', 'mobile' => '* Mobile',
            'work'           => '* Work',
            'home'           => '* Home',
            'fax'            => '* Fax',
            'other'          => '* Other',
            default          => '* Main',
        };
    }

    /**
     * Export all contacts in Google Contacts CSV import format.
     */
    public static function exportGoogleCsv(string $username, ?int $addressBookId = null): string
    {
        $contacts = self::allForUser($username, addressBookId: $addressBookId);
        $stream   = fopen('php://temp', 'r+');

        fputcsv($stream, [
            'Name', 'Given Name', 'Additional Name', 'Family Name',
            'Name Prefix', 'Name Suffix', 'Nickname', 'Birthday', 'Notes',
            'E-mail 1 - Label', 'E-mail 1 - Value',
            'E-mail 2 - Label', 'E-mail 2 - Value',
            'E-mail 3 - Label', 'E-mail 3 - Value',
            'Phone 1 - Label', 'Phone 1 - Value',
            'Phone 2 - Label', 'Phone 2 - Value',
            'Phone 3 - Label', 'Phone 3 - Value',
            'Address 1 - Label', 'Address 1 - Street', 'Address 1 - City',
            'Address 1 - Region', 'Address 1 - Postal Code', 'Address 1 - Country',
            'Address 2 - Label', 'Address 2 - Street', 'Address 2 - City',
            'Address 2 - Region', 'Address 2 - Postal Code', 'Address 2 - Country',
            'Organization 1 - Name', 'Organization 1 - Title',
            'Website 1 - Label', 'Website 1 - Value',
            'Website 2 - Label', 'Website 2 - Value',
        ]);

        foreach ($contacts as $c) {
            $emails = array_values($c['emails'] ?? []);
            $phones = array_values($c['phones'] ?? []);
            $addrs  = array_values($c['addresses'] ?? []);
            $urls   = array_values($c['urls'] ?? []);

            $row = [
                $c['fn']         ?? '',
                $c['first_name'] ?? '',
                '',
                $c['last_name']  ?? '',
                '', '',
                $c['nickname']   ?? '',
                $c['birthday']   ?? '',
                $c['note']       ?? '',
            ];

            for ($i = 0; $i < 3; $i++) {
                $row[] = isset($emails[$i]) ? self::googleEmailLabel($emails[$i]['type'] ?? '') : '';
                $row[] = $emails[$i]['address'] ?? '';
            }
            for ($i = 0; $i < 3; $i++) {
                $row[] = isset($phones[$i]) ? self::googlePhoneLabel($phones[$i]['type'] ?? '') : '';
                $row[] = $phones[$i]['number'] ?? '';
            }
            for ($i = 0; $i < 2; $i++) {
                $a = $addrs[$i] ?? null;
                $row[] = $a !== null ? (strtolower($a['type'] ?? '') === 'work' ? '* Work' : '* Home') : '';
                $row[] = $a['street']   ?? '';
                $row[] = $a['city']     ?? '';
                $row[] = $a['region']   ?? '';
                $row[] = $a['postcode'] ?? '';
                $row[] = $a['country']  ?? '';
            }
            $row[] = $c['org']   ?? '';
            $row[] = $c['title'] ?? '';
            for ($i = 0; $i < 2; $i++) {
                $u = $urls[$i] ?? null;
                $row[] = $u !== null ? (strtolower($u['type'] ?? '') === 'work' ? '* Work' : '* Homepage') : '';
                $row[] = $u['value'] ?? '';
            }

            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return (string) $csv;
    }

    /**
     * Export all contacts in Outlook CSV import format.
     */
    public static function exportOutlookCsv(string $username, ?int $addressBookId = null): string
    {
        $contacts = self::allForUser($username, addressBookId: $addressBookId);
        $stream   = fopen('php://temp', 'r+');

        fputcsv($stream, [
            'First Name', 'Middle Name', 'Last Name', 'Title', 'Suffix', 'Nickname',
            'Company', 'Department', 'Job Title',
            'Business Street', 'Business City', 'Business State',
            'Business Postal Code', 'Business Country/Region',
            'Home Street', 'Home City', 'Home State',
            'Home Postal Code', 'Home Country/Region',
            'E-mail Address', 'E-mail 2 Address', 'E-mail 3 Address',
            'Business Phone', 'Business Phone 2',
            'Home Phone', 'Mobile Phone', 'Fax',
            'Web Page', 'Birthday', 'Notes',
        ]);

        foreach ($contacts as $c) {
            $emails = array_values($c['emails'] ?? []);
            $phones = array_values($c['phones'] ?? []);

            $home = null;
            $work = null;
            foreach ($c['addresses'] ?? [] as $addr) {
                if (strtolower($addr['type'] ?? '') === 'work' && $work === null) {
                    $work = $addr;
                } elseif ($home === null) {
                    $home = $addr;
                }
            }

            $bizPhone = $bizPhone2 = $homePhone = $mobPhone = $faxPhone = '';
            foreach ($phones as $p) {
                $t = strtolower($p['type'] ?? '');
                if ($t === 'work' && $bizPhone === '') {
                    $bizPhone = $p['number'];
                } elseif ($t === 'work' && $bizPhone2 === '') {
                    $bizPhone2 = $p['number'];
                } elseif (in_array($t, ['cell', 'mobile'], true) && $mobPhone === '') {
                    $mobPhone = $p['number'];
                } elseif (in_array($t, ['home', 'voice'], true) && $homePhone === '') {
                    $homePhone = $p['number'];
                } elseif ($t === 'fax' && $faxPhone === '') {
                    $faxPhone = $p['number'];
                } elseif ($bizPhone === '') {
                    $bizPhone = $p['number'];
                }
            }

            fputcsv($stream, [
                $c['first_name'] ?? '', '', $c['last_name'] ?? '', '', '', $c['nickname'] ?? '',
                $c['org'] ?? '', '', $c['title'] ?? '',
                $work['street'] ?? '', $work['city'] ?? '', $work['region'] ?? '',
                $work['postcode'] ?? '', $work['country'] ?? '',
                $home['street'] ?? '', $home['city'] ?? '', $home['region'] ?? '',
                $home['postcode'] ?? '', $home['country'] ?? '',
                $emails[0]['address'] ?? '',
                $emails[1]['address'] ?? '',
                $emails[2]['address'] ?? '',
                $bizPhone, $bizPhone2, $homePhone, $mobPhone, $faxPhone,
                $c['urls'][0]['value'] ?? '',
                $c['birthday'] ?? '',
                $c['note'] ?? '',
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return (string) $csv;
    }

    // -------------------------------------------------------
    // Sync-token helper
    // -------------------------------------------------------

    private static function bumpSyncToken(int $abId, string $uri, int $op): void
    {
        $pdo = Database::getInstance();
        $pdo->prepare('UPDATE addressbooks SET synctoken = synctoken + 1 WHERE id = ?')->execute([$abId]);
        $stmt = $pdo->prepare('SELECT synctoken FROM addressbooks WHERE id = ?');
        $stmt->execute([$abId]);
        $token = (int) $stmt->fetchColumn();
        $pdo->prepare(
            'INSERT INTO addressbookchanges (uri, synctoken, addressbookid, operation) VALUES (?, ?, ?, ?)'
        )->execute([$uri, $token, $abId, $op]);
    }

    // -------------------------------------------------------
    // Auto-migration: group tables
    // -------------------------------------------------------

    private static function ensureGroupTables(): void
    {
        if (self::$groupTablesChecked) {
            return;
        }
        $pdo = Database::getInstance();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_groups` (
            `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `addressbook_id` INT UNSIGNED NOT NULL,
            `name`           VARCHAR(100) NOT NULL,
            `color`          VARCHAR(20) DEFAULT '',
            `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `idx_ab_name` (`addressbook_id`, `name`),
            FOREIGN KEY (`addressbook_id`) REFERENCES `addressbooks` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_group_members` (
            `group_id` INT UNSIGNED NOT NULL,
            `card_id`  INT UNSIGNED NOT NULL,
            PRIMARY KEY (`group_id`, `card_id`),
            FOREIGN KEY (`group_id`) REFERENCES `contact_groups` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`card_id`)  REFERENCES `cards` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$groupTablesChecked = true;
    }

    // -------------------------------------------------------
    // Auto-migration: history table
    // -------------------------------------------------------

    private static function ensureHistoryTable(): void
    {
        if (self::$historyTableChecked) {
            return;
        }
        $pdo = Database::getInstance();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_history` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `card_id`    INT UNSIGNED NOT NULL,
            `action`     VARCHAR(30) NOT NULL,
            `detail`     TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_card_id` (`card_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$historyTableChecked = true;
    }

    // -------------------------------------------------------
    // Starred
    // -------------------------------------------------------

    /**
     * Toggle the starred state of a contact. Returns the new starred state.
     */
    public static function toggleStar(int $id, string $username, ?int $addressBookId = null): bool
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT id, uri, carddata FROM cards WHERE addressbookid = ? AND (local_id = ? OR id = ?) ORDER BY CASE WHEN local_id = ? THEN 0 ELSE 1 END, id LIMIT 1'
        );
        $stmt->execute([$abId, $id, $id, $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        try {
            $vcard = Reader::read($row['carddata']);
            if (!($vcard instanceof VCard)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        $wasStarred = isset($vcard->{'X-CARDY-STARRED'}) && (string) $vcard->{'X-CARDY-STARRED'} === '1';
        unset($vcard->{'X-CARDY-STARRED'});
        if (!$wasStarred) {
            $vcard->add('X-CARDY-STARRED', '1');
        }

        $vcardData = $vcard->serialize();
        $pdo->prepare('UPDATE cards SET carddata = ?, etag = ?, size = ?, lastmodified = ? WHERE id = ?')
            ->execute([
                $vcardData,
                md5($vcardData),
                strlen($vcardData),
                time(),
                (int) $row['id'],
            ]);
        self::bumpSyncToken($abId, $row['uri'], 2);

        $newStarred = !$wasStarred;
        self::ensureHistoryTable();
        self::recordHistory((int) $row['id'], $newStarred ? 'starred' : 'unstarred', '');
        return $newStarred;
    }

    // -------------------------------------------------------
    // Groups
    // -------------------------------------------------------

    public static function getAllGroups(string $username, ?int $addressBookId = null): array
    {
        self::ensureGroupTables();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT id, name, color FROM contact_groups WHERE addressbook_id = ? ORDER BY name ASC'
        );
        $stmt->execute([$abId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function createGroup(string $username, string $name, string $color = '', ?int $addressBookId = null): int
    {
        self::ensureGroupTables();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            throw new \RuntimeException('No address book found.');
        }
        $name  = substr(trim($name), 0, 100);
        $color = substr((string) preg_replace('/[^#a-fA-F0-9]/', '', $color), 0, 7);
        if ($name === '') {
            throw new \RuntimeException('Group name cannot be empty.');
        }
        try {
            $pdo->prepare('INSERT INTO contact_groups (addressbook_id, name, color) VALUES (?, ?, ?)')
                ->execute([$abId, $name, $color]);
            return (int) $pdo->lastInsertId();
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000 || stripos($e->getMessage(), 'Duplicate') !== false) {
                throw new \RuntimeException("A group named \"{$name}\" already exists.");
            }
            throw $e;
        }
    }

    public static function updateGroup(int $groupId, string $username, string $name, string $color = '', ?int $addressBookId = null): void
    {
        self::ensureGroupTables();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return;
        }
        $name  = substr(trim($name), 0, 100);
        $color = substr((string) preg_replace('/[^#a-fA-F0-9]/', '', $color), 0, 7);
        if ($name === '') {
            throw new \RuntimeException('Group name cannot be empty.');
        }
        $pdo->prepare('UPDATE contact_groups SET name = ?, color = ? WHERE id = ? AND addressbook_id = ?')
            ->execute([$name, $color, $groupId, $abId]);
    }

    public static function deleteGroup(int $groupId, string $username, ?int $addressBookId = null): void
    {
        self::ensureGroupTables();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return;
        }
        $pdo->prepare('DELETE FROM contact_groups WHERE id = ? AND addressbook_id = ?')
            ->execute([$groupId, $abId]);
    }

    public static function getContactGroups(int $cardDbId): array
    {
        self::ensureGroupTables();
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT cg.id, cg.name, cg.color FROM contact_group_members cgm
             JOIN contact_groups cg ON cg.id = cgm.group_id
             WHERE cgm.card_id = ? ORDER BY cg.name ASC'
        );
        $stmt->execute([$cardDbId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function setContactGroups(int $cardDbId, array $groupIds): void
    {
        self::ensureGroupTables();
        $pdo = Database::getInstance();
        $pdo->prepare('DELETE FROM contact_group_members WHERE card_id = ?')->execute([$cardDbId]);
        $stmt = $pdo->prepare('INSERT IGNORE INTO contact_group_members (group_id, card_id) VALUES (?, ?)');
        foreach ($groupIds as $gid) {
            $gid = (int) $gid;
            if ($gid > 0) {
                $stmt->execute([$gid, $cardDbId]);
            }
        }
    }

    // -------------------------------------------------------
    // History / Activity log
    // -------------------------------------------------------

    public static function getHistory(int $cardDbId, int $limit = 25): array
    {
        self::ensureHistoryTable();
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT action, detail, created_at FROM contact_history
             WHERE card_id = ? ORDER BY created_at DESC, id DESC LIMIT ?'
        );
        $stmt->execute([$cardDbId, $limit]);
        return $stmt->fetchAll() ?: [];
    }

    private static function recordHistory(int $cardDbId, string $action, string $detail = ''): void
    {
        self::ensureHistoryTable();
        Database::getInstance()
            ->prepare('INSERT INTO contact_history (card_id, action, detail) VALUES (?, ?, ?)')
            ->execute([$cardDbId, $action, $detail]);
    }

    // -------------------------------------------------------
    // Duplicate detection
    // -------------------------------------------------------

    /**
     * Find potential duplicate contacts. Returns groups of contacts that share a name,
     * email address, or phone number (contacts flagged as ignore-duplicate are excluded).
     */
    public static function findDuplicates(string $username, ?int $addressBookId = null): array
    {
        $contacts = self::allForUser($username, addressBookId: $addressBookId);
        $byName   = [];
        $byEmail  = [];
        $byPhone  = [];

        foreach ($contacts as $c) {
            if (!empty($c['ignore_duplicate'])) {
                continue;
            }
            $fn = strtolower(trim($c['fn'] ?? ''));
            if ($fn !== '' && $fn !== 'unknown') {
                $byName[$fn][] = $c;
            }
            foreach ($c['emails'] ?? [] as $e) {
                $addr = strtolower(trim($e['address'] ?? ''));
                if ($addr !== '') {
                    $byEmail[$addr][] = $c;
                }
            }
            foreach ($c['phones'] ?? [] as $p) {
                $num = (string) preg_replace('/[^0-9+]/', '', $p['number'] ?? '');
                if (strlen($num) >= 7) {
                    $byPhone[$num][] = $c;
                }
            }
        }

        $seenPairs = [];
        $results   = [];

        $addGroup = static function (array $group, string $reason) use (&$seenPairs, &$results): void {
            if (count($group) < 2) {
                return;
            }
            $ids = array_column($group, 'id');
            sort($ids);
            $key = implode('-', $ids);
            if (isset($seenPairs[$key])) {
                return;
            }
            $seenPairs[$key]  = true;
            $results[]        = ['contacts' => array_values($group), 'reason' => $reason];
        };

        foreach ($byName as $name => $group) {
            $addGroup($group, 'Same name: "' . $name . '"');
        }
        foreach ($byEmail as $email => $group) {
            $addGroup($group, 'Same email: ' . $email);
        }
        foreach ($byPhone as $phone => $group) {
            $addGroup($group, 'Same phone: ' . $phone);
        }

        return $results;
    }

    /**
     * Toggle the ignore-duplicate flag on a contact. Returns the new flag state.
     */
    public static function toggleIgnoreDuplicate(int $id, string $username, ?int $addressBookId = null): bool
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT id, uri, carddata FROM cards WHERE addressbookid = ? AND (local_id = ? OR id = ?) ORDER BY CASE WHEN local_id = ? THEN 0 ELSE 1 END, id LIMIT 1'
        );
        $stmt->execute([$abId, $id, $id, $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        try {
            $vcard = Reader::read($row['carddata']);
            if (!($vcard instanceof VCard)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        $wasIgnored = isset($vcard->{'X-CARDY-NO-DUPLICATE'}) && (string) $vcard->{'X-CARDY-NO-DUPLICATE'} === '1';
        unset($vcard->{'X-CARDY-NO-DUPLICATE'});
        if (!$wasIgnored) {
            $vcard->add('X-CARDY-NO-DUPLICATE', '1');
        }

        $vcardData = $vcard->serialize();
        $pdo->prepare('UPDATE cards SET carddata = ?, etag = ?, size = ?, lastmodified = ? WHERE id = ?')
            ->execute([
                $vcardData,
                md5($vcardData),
                strlen($vcardData),
                time(),
                (int) $row['id'],
            ]);
        self::bumpSyncToken($abId, $row['uri'], 2);
        return !$wasIgnored;
    }

    // -------------------------------------------------------
    // Merge
    // -------------------------------------------------------

    /**
     * Merge two contacts: apply $mergedData to the "keep" contact, transfer group
     * memberships from the "discard" contact, then delete the discard contact.
     * Returns the local_id of the surviving contact.
     */
    public static function mergeContacts(int $keepId, int $discardId, string $username, array $mergedData, ?int $addressBookId = null): int
    {
        self::ensureLocalIdColumn();
        self::ensureGroupTables();
        self::ensureHistoryTable();

        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            throw new \RuntimeException('No address book found.');
        }

        $getRow = static function (int $id) use ($pdo, $abId): ?array {
            $stmt = $pdo->prepare(
                'SELECT id, uri, carddata FROM cards WHERE addressbookid = ? AND (local_id = ? OR id = ?) ORDER BY CASE WHEN local_id = ? THEN 0 ELSE 1 END, id LIMIT 1'
            );
            $stmt->execute([$abId, $id, $id, $id]);
            return $stmt->fetch() ?: null;
        };

        $keepRow    = $getRow($keepId);
        $discardRow = $getRow($discardId);
        if (!$keepRow || !$discardRow) {
            throw new \RuntimeException('One or both contacts not found.');
        }

        $keepDbId    = (int) $keepRow['id'];
        $discardDbId = (int) $discardRow['id'];

        // Transfer group memberships from discard to keep
        $stmt = $pdo->prepare('SELECT group_id FROM contact_group_members WHERE card_id = ?');
        $stmt->execute([$discardDbId]);
        $discardGroups = array_column($stmt->fetchAll(), 'group_id');
        if (!empty($discardGroups)) {
            $ins = $pdo->prepare('INSERT IGNORE INTO contact_group_members (group_id, card_id) VALUES (?, ?)');
            foreach ($discardGroups as $gid) {
                $ins->execute([(int) $gid, $keepDbId]);
            }
        }

        // Preserve UID of the kept contact
        $keepExisting      = self::parseVCard($keepRow['carddata']);
        $mergedData['uid'] = $keepExisting['uid'];

        try {
            $vcard = Reader::read($keepRow['carddata']);
            if (!($vcard instanceof VCard)) {
                $vcard = new VCard(['VERSION' => '3.0']);
            }
        } catch (\Exception $e) {
            $vcard = new VCard(['VERSION' => '3.0']);
        }
        self::applyManagedFields($vcard, $mergedData);
        $vcardData = $vcard->serialize();
        $pdo->prepare('UPDATE cards SET carddata = ?, etag = ?, size = ?, lastmodified = ? WHERE id = ?')
            ->execute([
                $vcardData,
                md5($vcardData),
                strlen($vcardData),
                time(),
                $keepDbId,
            ]);
        self::bumpSyncToken($abId, $keepRow['uri'], 2);
        self::recordHistory($keepDbId, 'merged', "Merged with contact #{$discardId}");

        // Delete the discard contact
        $pdo->prepare('DELETE FROM cards WHERE id = ?')->execute([$discardDbId]);
        self::bumpSyncToken($abId, $discardRow['uri'], 3);

        return $keepId;
    }

    // -------------------------------------------------------
    // Birthday reminders
    // -------------------------------------------------------

    /**
     * Returns contacts with a birthday in the next $days days, in ascending order.
     */
    public static function getUpcomingBirthdays(string $username, int $days = 30, ?int $addressBookId = null): array
    {
        $contacts = self::allForUser($username, addressBookId: $addressBookId);
        $now      = new \DateTime('today');
        $results  = [];

        foreach ($contacts as $c) {
            $bday = trim($c['birthday'] ?? '');
            if ($bday === '') {
                continue;
            }

            $month = $day = 0;
            if (preg_match('/^--(\d{2})-(\d{2})$/', $bday, $m)) {
                $month = (int) $m[1];
                $day   = (int) $m[2];
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $bday, $m)) {
                $month = (int) $m[2];
                $day   = (int) $m[3];
            } elseif (preg_match('/^(\d{2})-(\d{2})$/', $bday, $m)) {
                $month = (int) $m[1];
                $day   = (int) $m[2];
            } else {
                continue;
            }

            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                continue;
            }

            $thisYear = (int) $now->format('Y');
            try {
                $bdayThis = new \DateTime(sprintf('%04d-%02d-%02d', $thisYear, $month, $day));
            } catch (\Exception $e) {
                continue;
            }

            // If birthday already passed this year, look at next year
            if ($bdayThis < $now) {
                $bdayThis->setDate($thisYear + 1, $month, $day);
            }

            $diff = (int) $now->diff($bdayThis)->days;
            if ($diff <= $days) {
                $results[] = array_merge($c, [
                    'days_until'    => $diff,
                    'birthday_date' => sprintf('%02d/%02d', $day, $month),
                ]);
            }
        }

        usort($results, static fn(array $a, array $b) => $a['days_until'] <=> $b['days_until']);
        return $results;
    }

    // -------------------------------------------------------
    // Bulk operations
    // -------------------------------------------------------

    /**
     * Delete multiple contacts by local ID. Returns count of deleted contacts.
     */
    public static function bulkDelete(array $ids, string $username, ?int $addressBookId = null): int
    {
        $deleted = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                self::delete($id, $username, $addressBookId);
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Set the starred state on multiple contacts by local ID.
     */
    public static function bulkStar(array $ids, string $username, bool $starred, ?int $addressBookId = null): void
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return;
        }

        $getRow = $pdo->prepare(
            'SELECT id, uri, carddata FROM cards WHERE addressbookid = ? AND (local_id = ? OR id = ?) ORDER BY CASE WHEN local_id = ? THEN 0 ELSE 1 END, id LIMIT 1'
        );

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $getRow->execute([$abId, $id, $id, $id]);
            $row = $getRow->fetch();
            if (!$row) {
                continue;
            }

            try {
                $vcard = Reader::read($row['carddata']);
                if (!($vcard instanceof VCard)) {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }

            $currentlyStarred = isset($vcard->{'X-CARDY-STARRED'}) && (string) $vcard->{'X-CARDY-STARRED'} === '1';
            if ($currentlyStarred === $starred) {
                continue;
            }

            unset($vcard->{'X-CARDY-STARRED'});
            if ($starred) {
                $vcard->add('X-CARDY-STARRED', '1');
            }

            $vcardData = $vcard->serialize();
            $pdo->prepare('UPDATE cards SET carddata = ?, etag = ?, size = ?, lastmodified = ? WHERE id = ?')
                ->execute([
                    $vcardData,
                    md5($vcardData),
                    strlen($vcardData),
                    time(),
                    (int) $row['id'],
                ]);
            self::bumpSyncToken($abId, $row['uri'], 2);
        }
    }

    /**
     * Assign a group to multiple contacts by local ID.
     */
    public static function bulkAddGroup(array $ids, string $username, int $groupId, ?int $addressBookId = null): void
    {
        self::ensureLocalIdColumn();
        self::ensureGroupTables();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username, $addressBookId);
        if (!$abId) {
            return;
        }

        $getRow = $pdo->prepare(
            'SELECT id FROM cards WHERE addressbookid = ? AND (local_id = ? OR id = ?) ORDER BY CASE WHEN local_id = ? THEN 0 ELSE 1 END, id LIMIT 1'
        );
        $ins = $pdo->prepare('INSERT IGNORE INTO contact_group_members (group_id, card_id) VALUES (?, ?)');

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $getRow->execute([$abId, $id, $id, $id]);
            $row = $getRow->fetch();
            if ($row) {
                $ins->execute([$groupId, (int) $row['id']]);
            }
        }
    }
}
