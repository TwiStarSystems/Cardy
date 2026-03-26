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

        $allCalendars    = CalendarEvent::getCalendarsForUser($user['username']);
        $activeCalId     = $this->getActiveCalendarId($user['username']);
        $activeCal       = null;
        foreach ($allCalendars as $cal) {
            if ((int) $cal['calendarid'] === $activeCalId) {
                $activeCal = $cal;
                break;
            }
        }

        $events = CalendarEvent::allForUser($user['username'], $year, $month, $activeCalId);

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
        $user = $this->requireAuth();
        $this->render('calendar/event_form', [
            'user'    => $user,
            'event'   => null,
            'date'    => $_GET['date'] ?? date('Y-m-d'),
            'csrf'    => $this->csrfToken(),
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

        $this->render('calendar/event_form', [
            'user'  => $user,
            'event' => $event,
            'date'  => $event['start_date'] ?? date('Y-m-d'),
            'csrf'  => $this->csrfToken(),
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
        return [
            'type'        => $post['type']        ?? 'VEVENT',
            'summary'     => $post['summary']     ?? '',
            'description' => $post['description'] ?? '',
            'location'    => $post['location']    ?? '',
            'start_date'  => $post['start_date']  ?? date('Y-m-d'),
            'start_time'  => $post['start_time']  ?? '00:00',
            'end_date'    => $post['end_date']    ?? date('Y-m-d'),
            'end_time'    => $post['end_time']    ?? '01:00',
            'all_day'     => !empty($post['all_day']),
            'timezone'    => 'UTC',
            'uid'         => $post['uid']         ?? '',
        ];
    }
}
