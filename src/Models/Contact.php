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
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
        if (!$abId) {
            return [];
        }

        $stmt = $pdo->prepare(
            'SELECT id, uri, carddata, lastmodified FROM cards WHERE addressbookid = ? ORDER BY lastmodified DESC'
        );
        $stmt->execute([$abId]);
        $rows = $stmt->fetchAll();

        $contacts = [];
        foreach ($rows as $row) {
            $parsed = self::parseVCard($row['carddata']);
            if ($search && stripos($parsed['fn'] . ' ' . $parsed['email'] . ' ' . $parsed['org'], $search) === false) {
                continue;
            }
            $contacts[] = array_merge(['id' => $row['id'], 'uri' => $row['uri']], $parsed);
        }

        return $contacts;
    }

    public static function findById(int $id, string $username): ?array
    {
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
        if (!$abId) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM cards WHERE id = ? AND addressbookid = ?');
        $stmt->execute([$id, $abId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return array_merge(['id' => $row['id'], 'uri' => $row['uri']], self::parseVCard($row['carddata']));
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

    // -------------------------------------------------------
    // Write
    // -------------------------------------------------------

    public static function create(string $username, array $data): int
    {
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
        if (!$abId) {
            throw new \RuntimeException('No address book found for user.');
        }

        $uid      = 'cardy-' . bin2hex(random_bytes(16));
        $data['uid'] = $uid;
        $vcardData = self::buildVCard($data);
        $uri      = $uid . '.vcf';
        $etag     = md5($vcardData);
        $now      = time();

        $pdo->prepare(
            'INSERT INTO cards (addressbookid, carddata, uri, lastmodified, etag, size) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$abId, $vcardData, $uri, $now, $etag, strlen($vcardData)]);

        $cardId = (int) $pdo->lastInsertId();
        self::bumpSyncToken($abId, $uri, 1);

        return $cardId;
    }

    public static function update(int $id, string $username, array $data): void
    {
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
        if (!$abId) {
            return;
        }

        $stmt = $pdo->prepare('SELECT uri, carddata FROM cards WHERE id = ? AND addressbookid = ?');
        $stmt->execute([$id, $abId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        // Preserve original UID
        $existing = self::parseVCard($row['carddata']);
        $data['uid'] = $existing['uid'] ?: ('cardy-' . bin2hex(random_bytes(16)));

        $vcardData = self::buildVCard($data);
        $etag      = md5($vcardData);
        $now       = time();

        $pdo->prepare('UPDATE cards SET carddata = ?, etag = ?, size = ?, lastmodified = ? WHERE id = ?')
            ->execute([$vcardData, $etag, strlen($vcardData), $now, $id]);

        self::bumpSyncToken($abId, $row['uri'], 2);
    }

    public static function delete(int $id, string $username): void
    {
        $pdo  = Database::getInstance();
        $abId = self::getAddressBookId($username);
        if (!$abId) {
            return;
        }

        $stmt = $pdo->prepare('SELECT uri FROM cards WHERE id = ? AND addressbookid = ?');
        $stmt->execute([$id, $abId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $pdo->prepare('DELETE FROM cards WHERE id = ?')->execute([$id]);
        self::bumpSyncToken($abId, $row['uri'], 3);
    }

    public static function countForUser(string $username): int
    {
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
