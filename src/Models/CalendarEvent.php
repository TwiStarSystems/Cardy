<?php
declare(strict_types=1);

namespace Cardy\Models;

use Cardy\Database;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * CalendarEvent model — wraps the SabreDAV calendarobjects + calendarinstances tables
 * and uses sabre/vobject for iCalendar serialisation.
 */
class CalendarEvent
{
    // -------------------------------------------------------
    // Calendar helpers
    // -------------------------------------------------------

    public static function getCalendarId(string $username): ?int
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT calendarid FROM calendarinstances WHERE principaluri = ? ORDER BY id LIMIT 1"
        );
        $stmt->execute(["principals/{$username}"]);
        $row = $stmt->fetch();
        return $row ? (int) $row['calendarid'] : null;
    }

    public static function getCalendarsForUser(string $username): array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT ci.id, ci.calendarid, ci.displayname, ci.calendarcolor, ci.description
             FROM calendarinstances ci WHERE ci.principaluri = ? ORDER BY ci.id'
        );
        $stmt->execute(["principals/{$username}"]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------
    // List / fetch
    // -------------------------------------------------------

    public static function allForUser(string $username, ?int $year = null, ?int $month = null): array
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username);
        if (!$calId) {
            return [];
        }

        $sql    = 'SELECT id, uri, calendardata, firstoccurence, lastoccurence, componenttype FROM calendarobjects WHERE calendarid = ?';
        $params = [$calId];

        if ($year !== null && $month !== null) {
            $start    = mktime(0, 0, 0, $month, 1, $year);
            $end      = mktime(23, 59, 59, $month + 1, 0, $year);
            $sql     .= ' AND firstoccurence >= ? AND firstoccurence <= ?';
            $params[] = $start;
            $params[] = $end;
        }

        $stmt = $pdo->prepare($sql . ' ORDER BY firstoccurence ASC');
        $stmt->execute($params);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $parsed  = self::parseICal($row['calendardata']);
            $events[] = array_merge(['id' => $row['id'], 'uri' => $row['uri']], $parsed);
        }

        return $events;
    }

    public static function findById(int $id, string $username): ?array
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username);
        if (!$calId) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM calendarobjects WHERE id = ? AND calendarid = ?');
        $stmt->execute([$id, $calId]);
        $row  = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return array_merge(['id' => $row['id'], 'uri' => $row['uri']], self::parseICal($row['calendardata']));
    }

    public static function countUpcomingForUser(string $username): int
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username);
        if (!$calId) {
            return 0;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM calendarobjects WHERE calendarid = ? AND firstoccurence >= ? AND componenttype = ?'
        );
        $stmt->execute([$calId, time(), 'VEVENT']);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------
    // Parse / build
    // -------------------------------------------------------

    public static function parseICal(string $data): array
    {
        try {
            $vcal = Reader::read($data);
        } catch (\Exception $e) {
            return self::emptyFields();
        }

        $component = null;
        foreach (['VEVENT', 'VTODO', 'VJOURNAL'] as $type) {
            if (isset($vcal->{$type})) {
                $component = $vcal->{$type};
                $compType  = $type;
                break;
            }
        }

        if (!$component) {
            return self::emptyFields();
        }

        $dtStart  = isset($component->DTSTART) ? $component->DTSTART->getDateTime() : null;
        $dtEnd    = isset($component->DTEND)   ? $component->DTEND->getDateTime()   : null;
        $duration = isset($component->DURATION) ? (string) $component->DURATION     : null;

        if (!$dtEnd && $duration && $dtStart) {
            try {
                $interval = new \DateInterval($duration);
                $dtEnd    = (clone $dtStart)->add($interval);
            } catch (\Exception $e) {
                $dtEnd = $dtStart;
            }
        }

        $allDay = false;
        if (isset($component->DTSTART)) {
            $allDay = !($component->DTSTART->hasTime());
        }

        return [
            'uid'         => isset($component->UID)         ? (string) $component->UID         : '',
            'summary'     => isset($component->SUMMARY)     ? (string) $component->SUMMARY     : '',
            'description' => isset($component->DESCRIPTION) ? (string) $component->DESCRIPTION : '',
            'location'    => isset($component->LOCATION)    ? (string) $component->LOCATION    : '',
            'status'      => isset($component->STATUS)      ? (string) $component->STATUS      : '',
            'type'        => $compType ?? 'VEVENT',
            'all_day'     => $allDay,
            'start'       => $dtStart ? $dtStart->format('Y-m-d H:i') : '',
            'start_date'  => $dtStart ? $dtStart->format('Y-m-d')     : '',
            'start_time'  => $dtStart ? $dtStart->format('H:i')       : '',
            'end'         => $dtEnd   ? $dtEnd->format('Y-m-d H:i')   : '',
            'end_date'    => $dtEnd   ? $dtEnd->format('Y-m-d')       : '',
            'end_time'    => $dtEnd   ? $dtEnd->format('H:i')         : '',
        ];
    }

    private static function emptyFields(): array
    {
        return [
            'uid' => '', 'summary' => '', 'description' => '', 'location' => '',
            'status' => '', 'type' => 'VEVENT', 'all_day' => false,
            'start' => '', 'start_date' => '', 'start_time' => '',
            'end' => '',   'end_date' => '',   'end_time' => '',
        ];
    }

    public static function buildICal(array $data): string
    {
        $uid  = $data['uid'] ?: 'cardy-' . bin2hex(random_bytes(16));
        $type = strtoupper($data['type'] ?? 'VEVENT');
        if (!in_array($type, ['VEVENT', 'VTODO', 'VJOURNAL'])) {
            $type = 'VEVENT';
        }

        $vcal = new VCalendar();
        $vcal->VERSION = '2.0';
        $vcal->PRODID  = '-//TwiStar Systems//Cardy//EN';

        $comp              = $vcal->add($type);
        $comp->UID         = $uid;
        $comp->SUMMARY     = $data['summary'] ?? '';
        $comp->DESCRIPTION = $data['description'] ?? '';
        $comp->LOCATION    = $data['location'] ?? '';

        $tz = new \DateTimeZone($data['timezone'] ?? 'UTC');

        if (!empty($data['all_day'])) {
            $startStr = $data['start_date'] ?? date('Y-m-d');
            $endStr   = $data['end_date']   ?? $startStr;
            $comp->add('DTSTART', new \DateTime($startStr, $tz))->add(['VALUE' => 'DATE']);
            $comp->add('DTEND',   new \DateTime($endStr,   $tz))->add(['VALUE' => 'DATE']);
        } else {
            $startStr = ($data['start_date'] ?? date('Y-m-d')) . ' ' . ($data['start_time'] ?? '00:00');
            $endStr   = ($data['end_date']   ?? date('Y-m-d')) . ' ' . ($data['end_time']   ?? '01:00');
            $comp->DTSTART = new \DateTime($startStr, $tz);
            $comp->DTEND   = new \DateTime($endStr,   $tz);
        }

        $comp->DTSTAMP = new \DateTime('now', new \DateTimeZone('UTC'));

        return $vcal->serialize();
    }

    // -------------------------------------------------------
    // Write
    // -------------------------------------------------------

    public static function create(string $username, array $data): int
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username);
        if (!$calId) {
            throw new \RuntimeException('No calendar found for user.');
        }

        $uid       = 'cardy-' . bin2hex(random_bytes(16));
        $data['uid'] = $uid;
        $icalData  = self::buildICal($data);
        $uri       = $uid . '.ics';
        $etag      = md5($icalData);
        $now       = time();
        $compType  = strtoupper($data['type'] ?? 'VEVENT');
        [$first, $last] = self::extractOccurrences($icalData);

        $pdo->prepare(
            'INSERT INTO calendarobjects
             (calendardata, uri, calendarid, lastmodified, etag, size, componenttype, firstoccurence, lastoccurence, uid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$icalData, $uri, $calId, $now, $etag, strlen($icalData), $compType, $first, $last, $uid]);

        $objId = (int) $pdo->lastInsertId();
        self::bumpSyncToken($calId, $uri, 1);

        return $objId;
    }

    public static function update(int $id, string $username, array $data): void
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username);
        if (!$calId) {
            return;
        }

        $stmt = $pdo->prepare('SELECT uri, calendardata FROM calendarobjects WHERE id = ? AND calendarid = ?');
        $stmt->execute([$id, $calId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $existing    = self::parseICal($row['calendardata']);
        $data['uid'] = $existing['uid'] ?: ('cardy-' . bin2hex(random_bytes(16)));

        $icalData = self::buildICal($data);
        $etag     = md5($icalData);
        $now      = time();
        $compType = strtoupper($data['type'] ?? 'VEVENT');
        [$first, $last] = self::extractOccurrences($icalData);

        $pdo->prepare(
            'UPDATE calendarobjects
             SET calendardata = ?, etag = ?, size = ?, lastmodified = ?, componenttype = ?,
                 firstoccurence = ?, lastoccurence = ?
             WHERE id = ?'
        )->execute([$icalData, $etag, strlen($icalData), $now, $compType, $first, $last, $id]);

        self::bumpSyncToken($calId, $row['uri'], 2);
    }

    public static function delete(int $id, string $username): void
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username);
        if (!$calId) {
            return;
        }

        $stmt = $pdo->prepare('SELECT uri FROM calendarobjects WHERE id = ? AND calendarid = ?');
        $stmt->execute([$id, $calId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $pdo->prepare('DELETE FROM calendarobjects WHERE id = ?')->execute([$id]);
        self::bumpSyncToken($calId, $row['uri'], 3);
    }

    // -------------------------------------------------------
    // Sync-token helper
    // -------------------------------------------------------

    private static function bumpSyncToken(int $calId, string $uri, int $op): void
    {
        $pdo = Database::getInstance();
        $pdo->prepare('UPDATE calendars SET synctoken = synctoken + 1 WHERE id = ?')->execute([$calId]);
        $stmt = $pdo->prepare('SELECT synctoken FROM calendars WHERE id = ?');
        $stmt->execute([$calId]);
        $token = (int) $stmt->fetchColumn();
        $pdo->prepare(
            'INSERT INTO calendarchanges (uri, synctoken, calendarid, operation) VALUES (?, ?, ?, ?)'
        )->execute([$uri, $token, $calId, $op]);
    }

    private static function extractOccurrences(string $icalData): array
    {
        try {
            $vcal = Reader::read($icalData);
            foreach (['VEVENT', 'VTODO', 'VJOURNAL'] as $type) {
                if (isset($vcal->{$type})) {
                    $comp  = $vcal->{$type};
                    $start = isset($comp->DTSTART) ? $comp->DTSTART->getDateTime()->getTimestamp() : null;
                    $end   = null;
                    if (isset($comp->DTEND)) {
                        $end = $comp->DTEND->getDateTime()->getTimestamp();
                    } elseif ($start) {
                        $end = $start + 3600;
                    }
                    return [$start, $end ?? $start];
                }
            }
        } catch (\Exception $e) {
        }
        return [null, null];
    }
}
