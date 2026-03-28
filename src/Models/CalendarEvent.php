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

    public static function getCalendarId(string $username, ?int $explicit = null): ?int
    {
        if ($explicit !== null) {
            return $explicit;
        }
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
            'SELECT ci.calendarid, ci.displayname, ci.calendarcolor, ci.description, ci.uri
             FROM calendarinstances ci WHERE ci.principaluri = ? ORDER BY ci.calendarid ASC'
        );
        $stmt->execute(["principals/{$username}"]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------
    // Calendar management
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
        return $slug !== '' ? $slug : 'calendar';
    }

    private static function uniqueCalendarSlug(string $username, string $baseSlug): string
    {
        $pdo  = Database::getInstance();
        $slug = $baseSlug;
        $i    = 2;
        while (true) {
            $stmt = $pdo->prepare('SELECT calendarid FROM calendarinstances WHERE principaluri = ? AND uri = ?');
            $stmt->execute(["principals/{$username}", $slug]);
            if (!$stmt->fetch()) {
                break;
            }
            $slug = $baseSlug . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Create a new calendar for a user. The URL slug is generated from the display name
     * and is immutable after creation. Returns calendarid, uri, displayname, calendarcolor.
     */
    public static function createCalendar(string $username, string $displayName, string $color = '#9600E1'): array
    {
        $pdo         = Database::getInstance();
        $displayName = trim($displayName) ?: 'New Calendar';
        $baseSlug    = self::slugify($displayName);
        $slug        = self::uniqueCalendarSlug($username, $baseSlug);
        $color       = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#9600E1';

        $pdo->prepare('INSERT INTO calendars (synctoken, components) VALUES (1, ?)')->execute(['VEVENT,VTODO,VJOURNAL']);
        $calendarId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO calendarinstances (calendarid, principaluri, access, displayname, uri, description, calendarcolor, timezone)
             VALUES (?, ?, 1, ?, ?, ?, ?, ?)'
        )->execute([$calendarId, "principals/{$username}", $displayName, $slug, '', $color, 'UTC']);

        return [
            'calendarid'    => $calendarId,
            'uri'           => $slug,
            'displayname'   => $displayName,
            'calendarcolor' => $color,
        ];
    }

    /**
     * Rename a calendar's display name and/or color. The URI/slug is never changed.
     */
    public static function renameCalendar(int $calendarId, string $username, string $newName, string $newColor = ''): void
    {
        $pdo      = Database::getInstance();
        $newName  = trim($newName) ?: 'Calendar';
        $newColor = preg_match('/^#[0-9a-fA-F]{6}$/', $newColor) ? $newColor : '#9600E1';
        $pdo->prepare(
            'UPDATE calendarinstances SET displayname = ?, calendarcolor = ? WHERE calendarid = ? AND principaluri = ?'
        )->execute([$newName, $newColor, $calendarId, "principals/{$username}"]);
        // Bump the calendar synctoken so DAV clients (e.g. DAVx5) re-fetch properties.
        $pdo->prepare('UPDATE calendars SET synctoken = synctoken + 1 WHERE id = ?')->execute([$calendarId]);
    }

    /**
     * Delete a calendar and all its events. Throws if it's the user's last calendar.
     */
    public static function deleteCalendar(int $calendarId, string $username): void
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM calendarinstances WHERE principaluri = ?');
        $stmt->execute(["principals/{$username}"]);
        if ((int) $stmt->fetchColumn() <= 1) {
            throw new \RuntimeException('Cannot delete the only calendar.');
        }
        $stmt = $pdo->prepare('SELECT calendarid FROM calendarinstances WHERE calendarid = ? AND principaluri = ?');
        $stmt->execute([$calendarId, "principals/{$username}"]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Calendar not found.');
        }
        // Delete events, instances, and the base calendar (CASCADE handles calendarchanges)
        $pdo->prepare('DELETE FROM calendarobjects WHERE calendarid = ?')->execute([$calendarId]);
        $pdo->prepare('DELETE FROM calendarinstances WHERE calendarid = ?')->execute([$calendarId]);
        $pdo->prepare('DELETE FROM calendars WHERE id = ?')->execute([$calendarId]);
    }

    // -------------------------------------------------------
    // Count across all calendars
    // -------------------------------------------------------

    public static function countUpcomingForAllCalendars(string $username): int
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM calendarobjects co
             JOIN calendarinstances ci ON co.calendarid = ci.calendarid
             WHERE ci.principaluri = ? AND co.firstoccurence >= ? AND co.componenttype = ?'
        );
        $stmt->execute(["principals/{$username}", time(), 'VEVENT']);
        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------
    // List / fetch
    // -------------------------------------------------------

    public static function allForUser(string $username, ?int $year = null, ?int $month = null, ?int $calendarId = null): array
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username, $calendarId);
        if (!$calId) {
            return [];
        }

        $sql        = 'SELECT id, uri, calendardata FROM calendarobjects WHERE calendarid = ?';
        $params     = [$calId];
        $rangeStart = null;
        $rangeEnd   = null;

        if ($year !== null && $month !== null) {
            $rangeStart = mktime(0, 0, 0, $month, 1, $year);
            $rangeEnd   = mktime(23, 59, 59, $month + 1, 0, $year);
            // Overlap detection so recurring events from earlier months appear
            $sql       .= ' AND firstoccurence <= ? AND (lastoccurence >= ? OR lastoccurence IS NULL)';
            $params[]   = $rangeEnd;
            $params[]   = $rangeStart;
        }

        $stmt = $pdo->prepare($sql . ' ORDER BY firstoccurence ASC');
        $stmt->execute($params);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $isRecurring = $rangeStart !== null && (
                str_contains($row['calendardata'], "\nRRULE:") ||
                str_contains($row['calendardata'], "\r\nRRULE:")
            );
            if ($isRecurring) {
                foreach (self::expandInRange($row['calendardata'], (int) $row['id'], $row['uri'], $rangeStart, $rangeEnd) as $ev) {
                    $events[] = $ev;
                }
            } else {
                $events[] = array_merge(['id' => $row['id'], 'uri' => $row['uri']], self::parseICal($row['calendardata']));
            }
        }

        return $events;
    }

    public static function allForUserInRange(string $username, \DateTimeInterface $from, \DateTimeInterface $to, ?int $calendarId = null): array
    {
        $pdo     = Database::getInstance();
        $calId   = self::getCalendarId($username, $calendarId);
        if (!$calId) {
            return [];
        }
        $startTs = $from->getTimestamp();
        $endTs   = $to->getTimestamp();

        $stmt = $pdo->prepare(
            'SELECT id, uri, calendardata FROM calendarobjects
             WHERE calendarid = ? AND firstoccurence <= ? AND (lastoccurence >= ? OR lastoccurence IS NULL)
             ORDER BY firstoccurence ASC'
        );
        $stmt->execute([$calId, $endTs, $startTs]);

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $isRecurring = str_contains($row['calendardata'], "\nRRULE:") ||
                           str_contains($row['calendardata'], "\r\nRRULE:");
            if ($isRecurring) {
                foreach (self::expandInRange($row['calendardata'], (int) $row['id'], $row['uri'], $startTs, $endTs) as $ev) {
                    $events[] = $ev;
                }
            } else {
                $events[] = array_merge(['id' => $row['id'], 'uri' => $row['uri']], self::parseICal($row['calendardata']));
            }
        }
        return $events;
    }

    public static function findById(int $id, string $username, ?int $calendarId = null): ?array
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username, $calendarId);
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

    public static function countUpcomingForUser(string $username, ?int $calendarId = null): int
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username, $calendarId);
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

        // Timezone
        $timezone = 'UTC';
        if (isset($component->DTSTART)) {
            $tzIdParam = $component->DTSTART['TZID'];
            if ($tzIdParam) {
                $tzName = (string) $tzIdParam;
                try { new \DateTimeZone($tzName); $timezone = $tzName; } catch (\Exception $e) {}
            } elseif ($dtStart) {
                $tzName = $dtStart->getTimezone()->getName();
                if ($tzName !== '' && $tzName !== false) {
                    $timezone = $tzName;
                }
            }
        }

        // Visibility (CLASS property)
        $visibility = 'PUBLIC';
        if (isset($component->CLASS)) {
            $v = strtoupper((string) $component->CLASS);
            if (in_array($v, ['PUBLIC', 'PRIVATE', 'CONFIDENTIAL'], true)) {
                $visibility = $v;
            }
        }

        // Categories
        $categories = [];
        if (isset($component->CATEGORIES)) {
            foreach ($component->CATEGORIES as $cat) {
                foreach ($cat->getParts() as $part) {
                    $part = trim((string) $part);
                    if ($part !== '') {
                        $categories[] = $part;
                    }
                }
            }
        }

        // Color (RFC 7986 COLOR, fallback to Apple extension)
        $color = '';
        if (isset($component->COLOR)) {
            $color = (string) $component->COLOR;
        } elseif (isset($component->{'X-APPLE-CALENDAR-COLOR'})) {
            $color = (string) $component->{'X-APPLE-CALENDAR-COLOR'};
        }

        // Organizer
        $organizer = ['name' => '', 'email' => ''];
        if (isset($component->ORGANIZER)) {
            $orgProp          = $component->ORGANIZER;
            $orgUri           = (string) $orgProp;
            $organizer['email'] = (string) preg_replace('/^mailto:/i', '', $orgUri);
            $organizer['name']  = $orgProp['CN'] ? (string) $orgProp['CN'] : '';
        }

        // Attendees
        $attendees = [];
        if (isset($component->ATTENDEE)) {
            foreach ($component->ATTENDEE as $att) {
                $uri      = (string) $att;
                $email    = (string) preg_replace('/^mailto:/i', '', $uri);
                $name     = $att['CN']       ? (string) $att['CN']       : '';
                $rsvp     = $att['RSVP']     ? strtoupper((string) $att['RSVP'])     : 'FALSE';
                $partstat = $att['PARTSTAT'] ? strtoupper((string) $att['PARTSTAT']) : 'NEEDS-ACTION';
                if ($email !== '') {
                    $attendees[] = ['name' => $name, 'email' => $email, 'rsvp' => $rsvp, 'partstat' => $partstat];
                }
            }
        }

        // Recurring rule
        $rrule = null;
        if (isset($component->RRULE)) {
            $parts = $component->RRULE->getParts();
            $freq  = strtoupper($parts['FREQ'] ?? '');
            if (in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
                $rrule = [
                    'freq'     => $freq,
                    'interval' => isset($parts['INTERVAL']) ? (int) $parts['INTERVAL'] : 1,
                    'count'    => isset($parts['COUNT'])    ? (int) $parts['COUNT']    : null,
                    'until'    => isset($parts['UNTIL'])    ? (string) $parts['UNTIL'] : null,
                    'byday'    => isset($parts['BYDAY'])    ? (string) $parts['BYDAY'] : null,
                ];
            }
        }

        // Alarm — first VALARM DISPLAY trigger stored as minutes before event
        $alarmMinutes = null;
        if (isset($component->VALARM)) {
            $alarm = $component->VALARM;
            if (isset($alarm->TRIGGER)) {
                $trigger = (string) $alarm->TRIGGER;
                if (preg_match('/^-P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?)?$/', $trigger, $m)) {
                    $total = (int) ($m[1] ?? 0) * 1440 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
                    if ($total > 0) {
                        $alarmMinutes = $total;
                    }
                }
            }
        }

        return [
            'uid'           => isset($component->UID)         ? (string) $component->UID         : '',
            'summary'       => isset($component->SUMMARY)     ? (string) $component->SUMMARY     : '',
            'description'   => isset($component->DESCRIPTION) ? (string) $component->DESCRIPTION : '',
            'location'      => isset($component->LOCATION)    ? (string) $component->LOCATION    : '',
            'status'        => isset($component->STATUS)      ? (string) $component->STATUS      : '',
            'type'          => $compType ?? 'VEVENT',
            'all_day'       => $allDay,
            'start'         => $dtStart ? $dtStart->format('Y-m-d H:i') : '',
            'start_date'    => $dtStart ? $dtStart->format('Y-m-d')     : '',
            'start_time'    => $dtStart ? $dtStart->format('H:i')       : '',
            'end'           => $dtEnd   ? $dtEnd->format('Y-m-d H:i')   : '',
            'end_date'      => $dtEnd   ? $dtEnd->format('Y-m-d')       : '',
            'end_time'      => $dtEnd   ? $dtEnd->format('H:i')         : '',
            'timezone'      => $timezone,
            'visibility'    => $visibility,
            'categories'    => $categories,
            'color'         => $color,
            'organizer'     => $organizer,
            'attendees'     => $attendees,
            'rrule'         => $rrule,
            'alarm_minutes' => $alarmMinutes,
        ];
    }

    private static function emptyFields(): array
    {
        return [
            'uid' => '', 'summary' => '', 'description' => '', 'location' => '',
            'status' => '', 'type' => 'VEVENT', 'all_day' => false,
            'start' => '', 'start_date' => '', 'start_time' => '',
            'end' => '',   'end_date' => '',   'end_time' => '',
            'timezone' => 'UTC', 'visibility' => 'PUBLIC', 'categories' => [],
            'color' => '', 'organizer' => ['name' => '', 'email' => ''], 'attendees' => [],
            'rrule' => null, 'alarm_minutes' => null,
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

        // Status
        $status = strtoupper($data['status'] ?? '');
        if (in_array($status, ['CONFIRMED', 'TENTATIVE', 'CANCELLED'], true)) {
            $comp->STATUS = $status;
        }

        // Visibility
        $visibility = strtoupper($data['visibility'] ?? 'PUBLIC');
        if (in_array($visibility, ['PRIVATE', 'CONFIDENTIAL'], true)) {
            $comp->CLASS = $visibility;
        }

        // Categories
        $categories = $data['categories'] ?? [];
        if (is_string($categories)) {
            $categories = array_values(array_filter(array_map('trim', explode(',', $categories))));
        }
        if (!empty($categories)) {
            $comp->add('CATEGORIES', implode(',', $categories));
        }

        // Color
        if (!empty($data['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
            $comp->COLOR = $data['color'];
            $comp->add('X-APPLE-CALENDAR-COLOR', $data['color']);
        }

        // Organizer
        $orgEmail = trim($data['organizer']['email'] ?? '');
        if ($orgEmail !== '') {
            $orgParams = [];
            $orgName   = trim($data['organizer']['name'] ?? '');
            if ($orgName !== '') {
                $orgParams['CN'] = $orgName;
            }
            $comp->add('ORGANIZER', 'mailto:' . $orgEmail, $orgParams);
        }

        // Attendees
        foreach (($data['attendees'] ?? []) as $att) {
            $attEmail = trim($att['email'] ?? '');
            if ($attEmail === '') {
                continue;
            }
            $attParams = ['CUTYPE' => 'INDIVIDUAL', 'ROLE' => 'REQ-PARTICIPANT', 'PARTSTAT' => 'NEEDS-ACTION'];
            $attName   = trim($att['name'] ?? '');
            if ($attName !== '') {
                $attParams['CN'] = $attName;
            }
            if (!empty($att['rsvp']) && strtoupper((string) $att['rsvp']) === 'TRUE') {
                $attParams['RSVP'] = 'TRUE';
            }
            $comp->add('ATTENDEE', 'mailto:' . $attEmail, $attParams);
        }

        try {
            $tz = new \DateTimeZone($data['timezone'] ?? 'UTC');
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }

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

        // Recurring rule (RRULE)
        $rrule = $data['rrule'] ?? null;
        if (!empty($rrule['freq'])) {
            $rFreq = strtoupper($rrule['freq']);
            if (in_array($rFreq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
                $rruleStr = 'FREQ=' . $rFreq;
                $rInt = max(1, (int) ($rrule['interval'] ?? 1));
                if ($rInt > 1) {
                    $rruleStr .= ';INTERVAL=' . $rInt;
                }
                if (!empty($rrule['count'])) {
                    $rruleStr .= ';COUNT=' . max(1, (int) $rrule['count']);
                } elseif (!empty($rrule['until'])) {
                    $rUntil = preg_replace('/[^0-9TZ]/', '', trim((string) $rrule['until']));
                    if ($rUntil !== '') {
                        $rruleStr .= ';UNTIL=' . $rUntil;
                    }
                }
                $comp->add('RRULE', $rruleStr);
            }
        }

        // Alarm (VALARM)
        $alarmMinutes = max(0, (int) ($data['alarm_minutes'] ?? 0));
        if ($alarmMinutes > 0) {
            $aH      = (int) floor($alarmMinutes / 60);
            $aM      = $alarmMinutes % 60;
            $trigger = '-PT' . ($aH > 0 ? $aH . 'H' : '') . ($aM > 0 ? $aM . 'M' : '0M');
            $alarm   = $vcal->createComponent('VALARM');
            $alarm->add('ACTION', 'DISPLAY');
            $alarm->add('DESCRIPTION', 'Reminder');
            $alarm->add('TRIGGER', $trigger);
            $comp->add($alarm);
        }

        return $vcal->serialize();
    }

    // -------------------------------------------------------
    // Write
    // -------------------------------------------------------

    public static function create(string $username, array $data, ?int $calendarId = null): int
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username, $calendarId);
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

    public static function update(int $id, string $username, array $data, ?int $calendarId = null): void
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username, $calendarId);
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

    public static function delete(int $id, string $username, ?int $calendarId = null): void
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username, $calendarId);
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

    /**
     * Returns synthetic all-day events for contact birthdays and anniversaries in
     * the given month. These are generated from the address book on the fly and
     * are NOT stored in the database.
     */
    public static function getBirthdayEventsForMonth(string $username, int $year, int $month): array
    {
        $contacts = \Cardy\Models\Contact::allForUser($username);
        $events   = [];

        foreach ($contacts as $c) {
            $name = trim($c['fn'] ?? '');
            if ($name === '') {
                $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            }
            if ($name === '') {
                continue;
            }

            // Birthday
            $bday = trim($c['birthday'] ?? '');
            if ($bday !== '') {
                $bMonth = $bDay = 0;
                if (preg_match('/^--(\.{2})-(\.{2})$/', $bday, $m) ||
                    preg_match('/^--(\d{2})-(\d{2})$/', $bday, $m)) {
                    $bMonth = (int) $m[1];
                    $bDay   = (int) $m[2];
                } elseif (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/', $bday, $m)) {
                    $bMonth = (int) $m[2];
                    $bDay   = (int) $m[3];
                }
                if ($bMonth === $month && $bDay >= 1 && $bDay <= 31) {
                    $events[] = self::syntheticContactEvent(
                        sprintf('%04d-%02d-%02d', $year, $month, $bDay),
                        "\u{1F382} {$name}\u{2019}s Birthday",
                        'birthday'
                    );
                }
            }

            // Anniversaries (X-ANNIVERSARY / X-ABDATE values stored as date strings)
            foreach ($c['anniversaries'] ?? [] as $ann) {
                $ann    = trim((string) $ann);
                $aMonth = $aDay = 0;
                if (preg_match('/^--(\d{2})-(\d{2})$/', $ann, $m)) {
                    $aMonth = (int) $m[1];
                    $aDay   = (int) $m[2];
                } elseif (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/', $ann, $m)) {
                    $aMonth = (int) $m[2];
                    $aDay   = (int) $m[3];
                }
                if ($aMonth === $month && $aDay >= 1 && $aDay <= 31) {
                    $events[] = self::syntheticContactEvent(
                        sprintf('%04d-%02d-%02d', $year, $month, $aDay),
                        "\u{1F48C} {$name}\u{2019}s Anniversary",
                        'anniversary'
                    );
                }
            }
        }

        return $events;
    }

    private static function syntheticContactEvent(string $dateStr, string $summary, string $kind): array
    {
        return [
            'id'          => null,
            'uri'         => '',
            'uid'         => '',
            'summary'     => $summary,
            'description' => '',
            'location'    => '',
            'status'      => '',
            'type'        => 'VEVENT',
            'all_day'     => true,
            'start'       => $dateStr,
            'start_date'  => $dateStr,
            'start_time'  => '',
            'end'         => $dateStr,
            'end_date'    => $dateStr,
            'end_time'    => '',
            'timezone'    => 'UTC',
            'visibility'  => 'PUBLIC',
            'categories'  => [$kind],
            'color'       => '',
            'organizer'   => ['name' => '', 'email' => ''],
            'attendees'   => [],
            'event_kind'  => $kind,
        ];
    }

    private static function expandInRange(string $icalData, int $id, string $uri, int $startTs, int $endTs): array
    {
        try {
            $vcal = Reader::read($icalData);
            $from = (new \DateTime('@' . $startTs))->setTimezone(new \DateTimeZone('UTC'));
            $to   = (new \DateTime('@' . $endTs))->setTimezone(new \DateTimeZone('UTC'));
            $exp  = $vcal->expand($from, $to);

            $results = [];
            foreach (['VEVENT', 'VTODO', 'VJOURNAL'] as $type) {
                foreach ($exp->select($type) as $comp) {
                    $dtS  = isset($comp->DTSTART) ? $comp->DTSTART->getDateTime() : null;
                    $dtE  = isset($comp->DTEND)   ? $comp->DTEND->getDateTime()   : null;
                    $aDay = isset($comp->DTSTART) && !$comp->DTSTART->hasTime();
                    $results[] = [
                        'id'            => $id,   'uri'       => $uri,
                        'uid'           => isset($comp->UID)         ? (string) $comp->UID         : '',
                        'summary'       => isset($comp->SUMMARY)     ? (string) $comp->SUMMARY     : '',
                        'description'   => isset($comp->DESCRIPTION) ? (string) $comp->DESCRIPTION : '',
                        'location'      => isset($comp->LOCATION)    ? (string) $comp->LOCATION    : '',
                        'status'        => isset($comp->STATUS)      ? (string) $comp->STATUS      : '',
                        'type'          => $type,
                        'all_day'       => $aDay,
                        'start'         => $dtS ? $dtS->format('Y-m-d H:i') : '',
                        'start_date'    => $dtS ? $dtS->format('Y-m-d') : '',
                        'start_time'    => $dtS ? $dtS->format('H:i') : '',
                        'end'           => $dtE ? $dtE->format('Y-m-d H:i') : '',
                        'end_date'      => $dtE ? $dtE->format('Y-m-d') : '',
                        'end_time'      => $dtE ? $dtE->format('H:i') : '',
                        'timezone'      => 'UTC', 'visibility' => 'PUBLIC', 'categories' => [],
                        'color'         => '', 'organizer' => ['name' => '', 'email' => ''],
                        'attendees'     => [], 'rrule' => null, 'alarm_minutes' => null,
                        'is_recurring'  => true,
                    ];
                }
            }
            return $results;
        } catch (\Exception $e) {
            return [array_merge(['id' => $id, 'uri' => $uri], self::parseICal($icalData))];
        }
    }

    public static function exportCalendar(string $username, ?int $calendarId = null): string
    {
        $pdo   = Database::getInstance();
        $calId = self::getCalendarId($username, $calendarId);
        if (!$calId) {
            return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//TwiStar Systems//Cardy//EN\r\nEND:VCALENDAR\r\n";
        }
        $stmt = $pdo->prepare('SELECT calendardata FROM calendarobjects WHERE calendarid = ? ORDER BY firstoccurence ASC');
        $stmt->execute([$calId]);
        $components = [];
        foreach ($stmt->fetchAll() as $row) {
            try {
                $vcal = Reader::read($row['calendardata']);
                foreach (['VEVENT', 'VTODO', 'VJOURNAL'] as $type) {
                    foreach ($vcal->select($type) as $comp) {
                        $components[] = $comp->serialize();
                    }
                }
            } catch (\Exception $e) {}
        }
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//TwiStar Systems//Cardy//EN\r\n"
            . implode('', $components)
            . "END:VCALENDAR\r\n";
    }

    public static function importCalendar(string $username, string $icsData, ?int $calendarId = null): array
    {
        $result = ['imported' => 0, 'failed' => 0, 'errors' => []];
        try {
            $vcal = Reader::read($icsData);
        } catch (\Exception $e) {
            $result['errors'][] = 'Invalid iCal file: ' . $e->getMessage();
            $result['failed']++;
            return $result;
        }
        foreach (['VEVENT', 'VTODO', 'VJOURNAL'] as $type) {
            foreach ($vcal->select($type) as $comp) {
                try {
                    $wrapper          = new VCalendar();
                    $wrapper->VERSION = '2.0';
                    $wrapper->add(clone $comp);
                    $data         = self::parseICal($wrapper->serialize());
                    $data['type'] = $type;
                    self::create($username, $data, $calendarId);
                    $result['imported']++;
                } catch (\Exception $e) {
                    $result['failed']++;
                    $result['errors'][] = (isset($comp->SUMMARY) ? (string) $comp->SUMMARY : 'Unknown') . ': ' . $e->getMessage();
                }
            }
        }
        return $result;
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
                    // For recurring events, extend lastoccurence for overlap queries
                    if (isset($comp->RRULE)) {
                        $parts = $comp->RRULE->getParts();
                        if (!empty($parts['UNTIL'])) {
                            try {
                                $until = new \DateTime((string) $parts['UNTIL']);
                                $end   = max((int) ($end ?? 0), $until->getTimestamp());
                            } catch (\Exception $e) {}
                        } elseif (empty($parts['COUNT'])) {
                            $end = 32503680000; // ~year 3000
                        }
                    }
                    return [$start, $end ?? $start];
                }
            }
        } catch (\Exception $e) {
        }
        return [null, null];
    }
}
