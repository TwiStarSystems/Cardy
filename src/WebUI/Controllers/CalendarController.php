<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\CalendarEvent;
use Cardy\WebUI\Controller;

class CalendarController extends Controller
{
    // -------------------------------------------------------
    // Active calendar session helpers
    // -------------------------------------------------------

    private function getActiveCalendarId(string $username): ?int
    {
        $key = 'active_cal_' . $username;
        if (!empty($_SESSION[$key])) {
            $id  = (int) $_SESSION[$key];
            $cals = CalendarEvent::getCalendarsForUser($username);
            foreach ($cals as $cal) {
                if ((int) $cal['calendarid'] === $id) {
                    return $id;
                }
            }
            unset($_SESSION[$key]);
        }
        $id = CalendarEvent::getCalendarId($username);
        if ($id !== null) {
            $_SESSION[$key] = $id;
        }
        return $id;
    }

    private function setActiveCalendarId(string $username, int $id): void
    {
        $_SESSION['active_cal_' . $username] = $id;
    }

    // -------------------------------------------------------
    // Calendar views
    // -------------------------------------------------------

    public function index(): void
    {
        $user  = $this->requireAuth();
        $year  = (int) ($_GET['year']  ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('m'));

        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $allCalendars = CalendarEvent::getCalendarsForUser($user['username']);
        $activeCalId  = $this->getActiveCalendarId($user['username']);
        $activeCal    = null;
        foreach ($allCalendars as $cal) {
            if ((int) $cal['calendarid'] === $activeCalId) {
                $activeCal = $cal;
                break;
            }
        }

        $events = CalendarEvent::allForUser($user['username'], $year, $month, $activeCalId);

        // Inject birthday/anniversary events from contacts
        $contactEvents = CalendarEvent::getBirthdayEventsForMonth($user['username'], $year, $month);
        if (!empty($contactEvents)) {
            $events = array_merge($events, $contactEvents);
            usort($events, static fn($a, $b) => strcmp($a['start_date'] ?? '', $b['start_date'] ?? ''));
        }

        // Build event map: [day => [event, ...]]
        $eventMap = [];
        foreach ($events as $event) {
            if ($event['start_date']) {
                $day = (int) date('j', strtotime($event['start_date']));
                $eventMap[$day][] = $event;
            }
        }

        $this->render('calendar/index', [
            'user'         => $user,
            'year'         => $year,
            'month'        => $month,
            'events'       => $events,
            'eventMap'     => $eventMap,
            'allCalendars' => $allCalendars,
            'activeCal'    => $activeCal,
            'activeCalId'  => $activeCalId,
            'csrf'         => $this->csrfToken(),
            'flash'        => $this->getFlash(),
        ]);
    }

    public function create(): void
    {
        $user     = $this->requireAuth();
        $contacts = \Cardy\Models\Contact::allForUser($user['username']);
        $this->render('calendar/event_form', [
            'user'     => $user,
            'event'    => null,
            'date'     => $_GET['date'] ?? date('Y-m-d'),
            'contacts' => $contacts,
            'csrf'     => $this->csrfToken(),
        ]);
    }

    public function store(): void
    {
        $user       = $this->requireAuth();
        $this->verifyCsrf();
        $activeCalId = $this->getActiveCalendarId($user['username']);
        $data        = $this->extractFormData();

        try {
            CalendarEvent::create($user['username'], $data, $activeCalId);
            $this->flash('success', 'Event created successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create event: ' . $e->getMessage());
        }

        $this->redirect('/calendar');
    }

    public function edit(array $params): void
    {
        $user        = $this->requireAuth();
        $activeCalId = $this->getActiveCalendarId($user['username']);
        $event       = CalendarEvent::findById((int) $params['id'], $user['username'], $activeCalId);
        if (!$event) {
            $this->abort(404, 'Event not found.');
        }

        $contacts = \Cardy\Models\Contact::allForUser($user['username']);
        $this->render('calendar/event_form', [
            'user'     => $user,
            'event'    => $event,
            'date'     => $event['start_date'] ?? date('Y-m-d'),
            'contacts' => $contacts,
            'csrf'     => $this->csrfToken(),
        ]);
    }

    public function update(array $params): void
    {
        $user        = $this->requireAuth();
        $this->verifyCsrf();
        $activeCalId = $this->getActiveCalendarId($user['username']);
        $event       = CalendarEvent::findById((int) $params['id'], $user['username'], $activeCalId);
        if (!$event) {
            $this->abort(404, 'Event not found.');
        }

        $data        = $this->extractFormData();
        $data['uid'] = $event['uid'];

        try {
            CalendarEvent::update((int) $params['id'], $user['username'], $data, $activeCalId);
            $this->flash('success', 'Event updated successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to update event: ' . $e->getMessage());
        }

        $this->redirect('/calendar');
    }

    public function delete(array $params): void
    {
        $user        = $this->requireAuth();
        $this->verifyCsrf();
        $activeCalId = $this->getActiveCalendarId($user['username']);
        CalendarEvent::delete((int) $params['id'], $user['username'], $activeCalId);
        $this->flash('success', 'Event deleted.');
        $this->redirect('/calendar');
    }

    // -------------------------------------------------------
    // Calendar management endpoints
    // -------------------------------------------------------

    public function switchCalendar(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();
        $id   = (int) $params['id'];
        $cals = CalendarEvent::getCalendarsForUser($user['username']);
        foreach ($cals as $cal) {
            if ((int) $cal['calendarid'] === $id) {
                $this->setActiveCalendarId($user['username'], $id);
                break;
            }
        }
        $this->redirect('/calendar');
    }

    public function createCalendarAction(): void
    {
        $user        = $this->requireAuth();
        $this->verifyCsrf();
        $displayName = trim($_POST['displayname'] ?? '');
        $color       = trim($_POST['color'] ?? '#9600E1');
        if ($displayName === '') {
            $this->flash('error', 'Calendar name is required.');
            $this->redirect('/calendar');
            return;
        }
        try {
            $cal = CalendarEvent::createCalendar($user['username'], $displayName, $color);
            $this->setActiveCalendarId($user['username'], (int) $cal['calendarid']);
            $this->flash('success', 'Calendar "' . htmlspecialchars($cal['displayname']) . '" created. DAV URL: .../calendars/' . htmlspecialchars($user['username']) . '/' . htmlspecialchars($cal['uri']) . '/');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create calendar: ' . $e->getMessage());
        }
        $this->redirect('/calendar');
    }

    public function renameCalendarAction(array $params): void
    {
        $user        = $this->requireAuth();
        $this->verifyCsrf();
        $id          = (int) $params['id'];
        $displayName = trim($_POST['displayname'] ?? '');
        $color       = trim($_POST['color'] ?? '');
        if ($displayName === '') {
            $this->flash('error', 'Name cannot be empty.');
            $this->redirect('/calendar');
            return;
        }
        try {
            CalendarEvent::renameCalendar($id, $user['username'], $displayName, $color);
            $this->flash('success', 'Calendar renamed.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to rename: ' . $e->getMessage());
        }
        $this->redirect('/calendar');
    }

    public function deleteCalendarAction(array $params): void
    {
        $user     = $this->requireAuth();
        $this->verifyCsrf();
        $id       = (int) $params['id'];
        $activeId = $this->getActiveCalendarId($user['username']);
        try {
            CalendarEvent::deleteCalendar($id, $user['username']);
            if ($activeId === $id) {
                unset($_SESSION['active_cal_' . $user['username']]);
            }
            $this->flash('success', 'Calendar deleted.');
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/calendar');
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function extractFormData(): array
    {
        $post = $_POST;

        $timezone = trim($post['timezone'] ?? 'UTC');
        try { new \DateTimeZone($timezone); } catch (\Exception $e) { $timezone = 'UTC'; }

        $attEmails = $post['attendee_email'] ?? [];
        $attNames  = $post['attendee_name']  ?? [];
        $attRsvps  = $post['attendee_rsvp']  ?? [];
        if (!is_array($attEmails)) { $attEmails = [$attEmails]; }
        if (!is_array($attNames))  { $attNames  = [$attNames]; }
        if (!is_array($attRsvps))  { $attRsvps  = [$attRsvps]; }
        $attendees = [];
        foreach ($attEmails as $i => $email) {
            $email = trim((string) $email);
            if ($email !== '') {
                $attendees[] = [
                    'name'  => trim((string) ($attNames[$i] ?? '')),
                    'email' => $email,
                    'rsvp'  => !empty($attRsvps[$i]) ? 'TRUE' : 'FALSE',
                ];
            }
        }

        // Recurrence rule
        $rruleFreq = strtoupper(trim($post['rrule_freq'] ?? ''));
        $rrule     = null;
        if (in_array($rruleFreq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            $endType = $post['rrule_end_type'] ?? 'never';
            $until   = '';
            if ($endType === 'until') {
                $raw   = trim($post['rrule_until'] ?? '');
                $until = preg_replace('/[^0-9]/', '', $raw); // YYYYMMDD
            }
            $rrule = [
                'freq'     => $rruleFreq,
                'interval' => max(1, (int) ($post['rrule_interval'] ?? 1)),
                'count'    => $endType === 'count' ? max(1, (int) ($post['rrule_count'] ?? 1)) : null,
                'until'    => $endType === 'until' && $until !== '' ? $until : null,
                'byday'    => null,
            ];
        }

        return [
            'type'          => $post['type']        ?? 'VEVENT',
            'summary'       => $post['summary']     ?? '',
            'description'   => $post['description'] ?? '',
            'location'      => $post['location']    ?? '',
            'start_date'    => $post['start_date']  ?? date('Y-m-d'),
            'start_time'    => $post['start_time']  ?? '00:00',
            'end_date'      => $post['end_date']    ?? date('Y-m-d'),
            'end_time'      => $post['end_time']    ?? '01:00',
            'all_day'       => !empty($post['all_day']),
            'timezone'      => $timezone,
            'uid'           => $post['uid']  ?? '',
            'status'        => $post['status']     ?? '',
            'visibility'    => $post['visibility'] ?? 'PUBLIC',
            'categories'    => trim($post['categories'] ?? ''),
            'color'         => preg_match('/^#[0-9a-fA-F]{6}$/', $post['color'] ?? '') ? $post['color'] : '',
            'organizer'     => [
                'name'  => trim($post['organizer_name']  ?? ''),
                'email' => trim($post['organizer_email'] ?? ''),
            ],
            'attendees'     => $attendees,
            'rrule'         => $rrule,
            'alarm_minutes' => max(0, (int) ($post['alarm_minutes'] ?? 0)),
        ];
    }

    // -------------------------------------------------------
    // Calendar views — week, day, agenda
    // -------------------------------------------------------

    public function week(): void
    {
        $user        = $this->requireAuth();
        $activeCalId = $this->getActiveCalendarId($user['username']);
        $dateStr     = $_GET['date'] ?? date('Y-m-d');
        try { $anchor = new \DateTime($dateStr); } catch (\Exception $e) { $anchor = new \DateTime(); }

        $dow       = (int) $anchor->format('N'); // 1=Mon…7=Sun
        $weekStart = (clone $anchor)->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
        $weekEnd   = (clone $weekStart)->modify('+6 days')->setTime(23, 59, 59);

        $allEvents = CalendarEvent::allForUserInRange($user['username'], $weekStart, $weekEnd, $activeCalId);

        // Merge birthday events (may span two months)
        $birthdays = CalendarEvent::getBirthdayEventsForMonth($user['username'], (int) $weekStart->format('Y'), (int) $weekStart->format('n'));
        if ($weekEnd->format('Yn') !== $weekStart->format('Yn')) {
            $birthdays = array_merge($birthdays, CalendarEvent::getBirthdayEventsForMonth($user['username'], (int) $weekEnd->format('Y'), (int) $weekEnd->format('n')));
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day     = (clone $weekStart)->modify("+{$i} days");
            $dateKey = $day->format('Y-m-d');
            $dayEvs  = [];
            foreach (array_merge($allEvents, $birthdays) as $ev) {
                if (($ev['start_date'] ?? '') === $dateKey) {
                    $dayEvs[] = $ev;
                }
            }
            usort($dayEvs, static fn($a, $b) => strcmp($a['start_time'] ?? '', $b['start_time'] ?? ''));
            $days[] = ['date' => $day, 'dateKey' => $dateKey, 'isToday' => $dateKey === date('Y-m-d'), 'events' => $dayEvs];
        }

        [$allCalendars, $activeCal] = $this->calendarMeta($user['username'], $activeCalId);
        $this->render('calendar/week', [
            'user'        => $user,
            'weekStart'   => $weekStart,
            'weekEnd'     => $weekEnd,
            'days'        => $days,
            'prevDate'    => (clone $weekStart)->modify('-7 days')->format('Y-m-d'),
            'nextDate'    => (clone $weekStart)->modify('+7 days')->format('Y-m-d'),
            'allCalendars' => $allCalendars,
            'activeCal'   => $activeCal,
            'activeCalId' => $activeCalId,
            'csrf'        => $this->csrfToken(),
            'flash'       => $this->getFlash(),
        ]);
    }

    public function dayView(): void
    {
        $user        = $this->requireAuth();
        $activeCalId = $this->getActiveCalendarId($user['username']);
        $dateStr     = $_GET['date'] ?? date('Y-m-d');
        try { $day = new \DateTime($dateStr); } catch (\Exception $e) { $day = new \DateTime(); $dateStr = $day->format('Y-m-d'); }

        $dayStart = (clone $day)->setTime(0, 0, 0);
        $dayEnd   = (clone $day)->setTime(23, 59, 59);

        $allEvents = CalendarEvent::allForUserInRange($user['username'], $dayStart, $dayEnd, $activeCalId);
        $birthdays = array_filter(
            CalendarEvent::getBirthdayEventsForMonth($user['username'], (int) $day->format('Y'), (int) $day->format('n')),
            static fn($ev) => ($ev['start_date'] ?? '') === $dateStr
        );
        $events = array_merge($allEvents, array_values($birthdays));
        usort($events, static fn($a, $b) => strcmp(
            $a['all_day'] ? '' : ($a['start_time'] ?? ''),
            $b['all_day'] ? '' : ($b['start_time'] ?? '')
        ));

        [$allCalendars, $activeCal] = $this->calendarMeta($user['username'], $activeCalId);
        $this->render('calendar/day', [
            'user'        => $user,
            'day'         => $day,
            'dateStr'     => $dateStr,
            'events'      => $events,
            'prevDate'    => (clone $day)->modify('-1 day')->format('Y-m-d'),
            'nextDate'    => (clone $day)->modify('+1 day')->format('Y-m-d'),
            'allCalendars' => $allCalendars,
            'activeCal'   => $activeCal,
            'activeCalId' => $activeCalId,
            'csrf'        => $this->csrfToken(),
            'flash'       => $this->getFlash(),
        ]);
    }

    public function agenda(): void
    {
        $user        = $this->requireAuth();
        $activeCalId = $this->getActiveCalendarId($user['username']);
        $dateStr     = $_GET['date'] ?? date('Y-m-d');
        try { $from = new \DateTime($dateStr); } catch (\Exception $e) { $from = new \DateTime(); }
        $from->setTime(0, 0, 0);
        $to = (clone $from)->modify('+60 days')->setTime(23, 59, 59);

        $events = CalendarEvent::allForUserInRange($user['username'], $from, $to, $activeCalId);

        // Span months covered by the range (typically 2-3)
        $seen   = [];
        $cursor = clone $from;
        while ($cursor <= $to) {
            $key = $cursor->format('Y') . '-' . $cursor->format('n');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $events = array_merge($events, CalendarEvent::getBirthdayEventsForMonth($user['username'], (int) $cursor->format('Y'), (int) $cursor->format('n')));
            }
            $cursor->modify('+1 month');
        }

        usort($events, static fn($a, $b) => strcmp($a['start'] ?? '', $b['start'] ?? ''));

        $fromStr  = $from->format('Y-m-d');
        $toStr    = $to->format('Y-m-d');
        $grouped  = [];
        foreach ($events as $ev) {
            $d = $ev['start_date'] ?? '';
            if ($d >= $fromStr && $d <= $toStr) {
                $grouped[$d][] = $ev;
            }
        }
        ksort($grouped);

        [$allCalendars, $activeCal] = $this->calendarMeta($user['username'], $activeCalId);
        $this->render('calendar/agenda', [
            'user'        => $user,
            'grouped'     => $grouped,
            'from'        => $from,
            'to'          => $to,
            'allCalendars' => $allCalendars,
            'activeCal'   => $activeCal,
            'activeCalId' => $activeCalId,
            'csrf'        => $this->csrfToken(),
            'flash'       => $this->getFlash(),
        ]);
    }

    public function exportCalendar(): void
    {
        $user        = $this->requireAuth();
        $activeCalId = $this->getActiveCalendarId($user['username']);
        $icsData     = CalendarEvent::exportCalendar($user['username'], $activeCalId);
        $filename    = 'cardy-' . date('Y-m-d') . '.ics';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($icsData));
        echo $icsData;
        exit;
    }

    public function importCalendarForm(): void
    {
        $user = $this->requireAuth();
        $this->render('calendar/import', [
            'user'  => $user,
            'csrf'  => $this->csrfToken(),
            'flash' => $this->getFlash(),
        ]);
    }

    public function importCalendarAction(): void
    {
        $user        = $this->requireAuth();
        $this->verifyCsrf();
        $activeCalId = $this->getActiveCalendarId($user['username']);

        $file = $_FILES['ics_file'] ?? null;
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please select a valid .ics file to import.');
            $this->redirect('/calendar/import');
            return;
        }
        $icsData = @file_get_contents($file['tmp_name']);
        if ($icsData === false || $icsData === '') {
            $this->flash('error', 'The uploaded file is empty or could not be read.');
            $this->redirect('/calendar/import');
            return;
        }
        $result  = CalendarEvent::importCalendar($user['username'], $icsData, $activeCalId);
        $message = 'Imported ' . $result['imported'] . ' event(s).';
        if ($result['failed'] > 0) {
            $message .= ' Failed: ' . $result['failed'] . '.';
            if (!empty($result['errors'])) {
                $message .= ' ' . implode(' | ', array_slice($result['errors'], 0, 2));
            }
            $this->flash('error', $message);
        } else {
            $this->flash('success', $message);
        }
        $this->redirect('/calendar');
    }

    // -------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------

    private function calendarMeta(string $username, ?int $activeCalId): array
    {
        $allCalendars = CalendarEvent::getCalendarsForUser($username);
        $activeCal    = null;
        foreach ($allCalendars as $cal) {
            if ((int) $cal['calendarid'] === $activeCalId) {
                $activeCal = $cal;
                break;
            }
        }
        return [$allCalendars, $activeCal];
    }
}

