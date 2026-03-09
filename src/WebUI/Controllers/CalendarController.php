<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\CalendarEvent;
use Cardy\WebUI\Controller;

class CalendarController extends Controller
{
    public function index(): void
    {
        $user  = $this->requireAuth();
        $year  = (int) ($_GET['year']  ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('m'));

        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $events = CalendarEvent::allForUser($user['username'], $year, $month);

        // Build event map: [day => [event, ...]]
        $eventMap = [];
        foreach ($events as $event) {
            if ($event['start_date']) {
                $day = (int) date('j', strtotime($event['start_date']));
                $eventMap[$day][] = $event;
            }
        }

        $this->render('calendar/index', [
            'user'     => $user,
            'year'     => $year,
            'month'    => $month,
            'events'   => $events,
            'eventMap' => $eventMap,
            'csrf'     => $this->csrfToken(),
            'flash'    => $this->getFlash(),
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
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $data = $this->extractFormData();

        try {
            CalendarEvent::create($user['username'], $data);
            $this->flash('success', 'Event created successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create event: ' . $e->getMessage());
        }

        $this->redirect('/calendar');
    }

    public function edit(array $params): void
    {
        $user  = $this->requireAuth();
        $event = CalendarEvent::findById((int) $params['id'], $user['username']);
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
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $event = CalendarEvent::findById((int) $params['id'], $user['username']);
        if (!$event) {
            $this->abort(404, 'Event not found.');
        }

        $data        = $this->extractFormData();
        $data['uid'] = $event['uid'];

        try {
            CalendarEvent::update((int) $params['id'], $user['username'], $data);
            $this->flash('success', 'Event updated successfully.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to update event: ' . $e->getMessage());
        }

        $this->redirect('/calendar');
    }

    public function delete(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        CalendarEvent::delete((int) $params['id'], $user['username']);
        $this->flash('success', 'Event deleted.');
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
