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
    private static bool $localIdChecked = false;

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

    public static function getAddressBookId(string $username): ?int
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT id FROM addressbooks WHERE principaluri = ? ORDER BY id LIMIT 1"
        );
        $stmt->execute(["principals/{$username}"]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    // -------------------------------------------------------
    // List / fetch
    // -------------------------------------------------------

    public static function allForUser(string $username, string $search = ''): array
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
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
                'id'    => (int) ($row['local_id'] ?: $row['id']),
                'db_id' => (int) $row['id'],
                'uri'   => $row['uri'],
            ], $parsed);
        }

        return $contacts;
    }

    public static function findById(int $id, string $username): ?array
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
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

        return array_merge([
            'id'    => (int) ($row['local_id'] ?: $row['id']),
            'db_id' => (int) $row['id'],
            'uri'   => $row['uri'],
        ], self::parseVCard($row['carddata']));
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
        if (isset($vcard->PHOTO)) {
            $photo = base64_encode((string) $vcard->PHOTO);
        }

        $nameParts = ['', '', '', '', ''];
        if (isset($vcard->N)) {
            $nameParts = $vcard->N->getParts();
            while (count($nameParts) < 5) {
                $nameParts[] = '';
            }
        }

        return [
            'fn'          => isset($vcard->FN)  ? (string) $vcard->FN  : '',
            'last_name'   => $nameParts[0],
            'first_name'  => $nameParts[1],
            'org'         => isset($vcard->ORG)  ? (string) $vcard->ORG  : '',
            'title'       => isset($vcard->TITLE) ? (string) $vcard->TITLE : '',
            'email'       => !empty($emails) ? $emails[0]['address'] : '',
            'emails'      => $emails,
            'phone'       => !empty($phones) ? $phones[0]['number'] : '',
            'phones'      => $phones,
            'addresses'   => $addresses,
            'birthday'    => isset($vcard->BDAY)  ? (string) $vcard->BDAY  : '',
            'note'        => isset($vcard->NOTE)  ? (string) $vcard->NOTE  : '',
            'photo'       => $photo,
            'uid'         => isset($vcard->UID)   ? (string) $vcard->UID   : '',
        ];
    }

    private static function emptyFields(): array
    {
        return [
            'fn' => '', 'last_name' => '', 'first_name' => '', 'org' => '',
            'title' => '', 'email' => '', 'emails' => [], 'phone' => '',
            'phones' => [], 'addresses' => [], 'birthday' => '', 'note' => '',
            'photo' => '', 'uid' => '',
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
            $prop = $vcard->add('ADR', [
                '',
                '',
                $a['street']   ?? '',
                $a['city']     ?? '',
                $a['region']   ?? '',
                $a['postcode'] ?? '',
                $a['country']  ?? '',
            ]);
            if (!empty($a['type'])) {
                $prop->add('TYPE', strtoupper($a['type']));
            }
        }

        if (!empty($data['birthday'])) {
            $vcard->add('BDAY', $data['birthday']);
        }
        if (!empty($data['note'])) {
            $vcard->add('NOTE', $data['note']);
        }

        return $vcard->serialize();
    }

    private static function applyManagedFields(VCard $vcard, array $data): void
    {
        $uid = $data['uid'] ?: 'cardy-' . bin2hex(random_bytes(16));

        unset($vcard->FN);
        unset($vcard->N);
        unset($vcard->ORG);
        unset($vcard->TITLE);
        unset($vcard->EMAIL);
        unset($vcard->TEL);
        unset($vcard->ADR);
        unset($vcard->BDAY);
        unset($vcard->NOTE);
        unset($vcard->UID);

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
            $prop = $vcard->add('ADR', [
                '',
                '',
                $a['street']   ?? '',
                $a['city']     ?? '',
                $a['region']   ?? '',
                $a['postcode'] ?? '',
                $a['country']  ?? '',
            ]);
            if (!empty($a['type'])) {
                $prop->add('TYPE', strtoupper($a['type']));
            }
        }

        if (!empty($data['birthday'])) {
            $vcard->add('BDAY', $data['birthday']);
        }
        if (!empty($data['note'])) {
            $vcard->add('NOTE', $data['note']);
        }
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

    public static function importVCardData(string $username, string $rawData): array
    {
        $abId = self::getAddressBookId($username);
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

    public static function create(string $username, array $data): int
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
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

        return $cardId;
    }

    public static function update(int $id, string $username, array $data): void
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
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
    }

    public static function delete(int $id, string $username): void
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
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

        $pdo->prepare('DELETE FROM cards WHERE id = ?')->execute([(int) $row['id']]);
        self::bumpSyncToken($abId, $row['uri'], 3);
    }

    public static function countForUser(string $username): int
    {
        self::ensureLocalIdColumn();
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
        if (!$abId) {
            return 0;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cards WHERE addressbookid = ?');
        $stmt->execute([$abId]);
        return (int) $stmt->fetchColumn();
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
}
