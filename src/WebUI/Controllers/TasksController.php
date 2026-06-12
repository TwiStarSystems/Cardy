<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\CalendarEvent;
use Cardy\WebUI\Controller;

class TasksController extends Controller
{
    // -------------------------------------------------------
    // Active calendar session helpers (shared logic with Calendar)
    // -------------------------------------------------------

    private function getActiveCalendarId(string $username): ?int
    {
        $key = 'active_cal_' . $username;
        if (!empty($_SESSION[$key])) {
            $id   = (int) $_SESSION[$key];
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

    // -------------------------------------------------------
    // Task list
    // -------------------------------------------------------

    public function index(): void
    {
        $user   = $this->requireAuth();
        $filter = $_GET['filter'] ?? 'all';   // all | active | completed | cancelled

        $allTasks = CalendarEvent::allTasksForUser($user['username']);

        // Sort: incomplete first (by due date), then completed
        usort($allTasks, static function (array $a, array $b): int {
            $aComplete = strtoupper($a['status'] ?? '') === 'COMPLETED';
            $bComplete = strtoupper($b['status'] ?? '') === 'COMPLETED';
            if ($aComplete !== $bComplete) {
                return $aComplete ? 1 : -1;
            }
            $aDue = $a['due_date'] ?: '9999-12-31';
            $bDue = $b['due_date'] ?: '9999-12-31';
            return strcmp($aDue, $bDue);
        });

        // Apply filter
        $tasks = array_values(array_filter($allTasks, static function (array $t) use ($filter): bool {
            $status = strtoupper($t['status'] ?? 'NEEDS-ACTION');
            if ($status === '') {
                $status = 'NEEDS-ACTION';
            }
            return match ($filter) {
                'active'    => in_array($status, ['NEEDS-ACTION', 'IN-PROCESS'], true),
                'completed' => $status === 'COMPLETED',
                'cancelled' => $status === 'CANCELLED',
                default     => true,
            };
        }));

        $counts = [
            'all'       => count($allTasks),
            'active'    => count(array_filter($allTasks, static fn($t) => in_array(strtoupper($t['status'] ?? 'NEEDS-ACTION'), ['NEEDS-ACTION', 'IN-PROCESS', ''], true))),
            'completed' => count(array_filter($allTasks, static fn($t) => strtoupper($t['status'] ?? '') === 'COMPLETED')),
            'cancelled' => count(array_filter($allTasks, static fn($t) => strtoupper($t['status'] ?? '') === 'CANCELLED')),
        ];

        $allCalendars = CalendarEvent::getCalendarsForUser($user['username']);

        $this->render('tasks/index', [
            'user'         => $user,
            'tasks'        => $tasks,
            'filter'       => $filter,
            'counts'       => $counts,
            'allCalendars' => $allCalendars,
            'csrf'         => $this->csrfToken(),
            'flash'        => $this->getFlash(),
        ]);
    }

    // -------------------------------------------------------
    // Create
    // -------------------------------------------------------

    public function create(): void
    {
        $user         = $this->requireAuth();
        $allCalendars = CalendarEvent::getCalendarsForUser($user['username']);
        $activeCalId  = $this->getActiveCalendarId($user['username']);

        $this->render('tasks/form', [
            'user'         => $user,
            'task'         => null,
            'allCalendars' => $allCalendars,
            'activeCalId'  => $activeCalId,
            'csrf'         => $this->csrfToken(),
        ]);
    }

    public function store(): void
    {
        $user        = $this->requireAuth();
        $this->verifyCsrf();
        $calendarId  = (int) ($_POST['calendar_id'] ?? 0) ?: $this->getActiveCalendarId($user['username']);
        $data        = $this->extractTaskFormData();

        try {
            CalendarEvent::create($user['username'], $data, $calendarId);
            $this->flash('success', 'Task created.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create task: ' . $e->getMessage());
        }

        $this->redirect('/tasks');
    }

    // -------------------------------------------------------
    // Edit / Update
    // -------------------------------------------------------

    public function edit(array $params): void
    {
        $user = $this->requireAuth();
        $task = CalendarEvent::findTaskByIdForUser((int) $params['id'], $user['username']);
        if (!$task) {
            $this->abort(404, 'Task not found.');
        }

        $allCalendars = CalendarEvent::getCalendarsForUser($user['username']);

        $this->render('tasks/form', [
            'user'         => $user,
            'task'         => $task,
            'allCalendars' => $allCalendars,
            'activeCalId'  => $task['calendar_id'],
            'csrf'         => $this->csrfToken(),
        ]);
    }

    public function update(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();
        $task = CalendarEvent::findTaskByIdForUser((int) $params['id'], $user['username']);
        if (!$task) {
            $this->abort(404, 'Task not found.');
        }

        $calendarId = (int) ($_POST['calendar_id'] ?? 0) ?: (int) $task['calendar_id'];
        $data        = $this->extractTaskFormData();
        $data['uid'] = $task['uid'];

        try {
            CalendarEvent::update((int) $params['id'], $user['username'], $data, $calendarId);
            $this->flash('success', 'Task updated.');
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to update task: ' . $e->getMessage());
        }

        $this->redirect('/tasks');
    }

    // -------------------------------------------------------
    // Delete
    // -------------------------------------------------------

    public function delete(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();
        $task = CalendarEvent::findTaskByIdForUser((int) $params['id'], $user['username']);
        if (!$task) {
            $this->abort(404, 'Task not found.');
        }

        CalendarEvent::delete((int) $params['id'], $user['username'], (int) $task['calendar_id']);
        $this->flash('success', 'Task deleted.');
        $this->redirect('/tasks');
    }

    // -------------------------------------------------------
    // Toggle completion
    // -------------------------------------------------------

    public function toggleComplete(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();
        $task = CalendarEvent::findTaskByIdForUser((int) $params['id'], $user['username']);
        if (!$task) {
            $this->abort(404, 'Task not found.');
        }

        $currentStatus = strtoupper($task['status'] ?? 'NEEDS-ACTION');
        $data = $task;
        if ($currentStatus === 'COMPLETED') {
            $data['status']           = 'NEEDS-ACTION';
            $data['percent_complete'] = 0;
        } else {
            $data['status']           = 'COMPLETED';
            $data['percent_complete'] = 100;
        }

        try {
            CalendarEvent::update((int) $params['id'], $user['username'], $data, (int) $task['calendar_id']);
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to update task.');
        }

        $back = $_SERVER['HTTP_REFERER'] ?? '/tasks';
        // Only follow relative same-origin paths; reject absolute URLs and protocol-relative refs
        if (!preg_match('/^\\/(?!\\/)/u', $back)) {
            $back = '/tasks';
        }
        $this->redirect($back);
    }

    // -------------------------------------------------------
    // Export tasks as .ics
    // -------------------------------------------------------

    public function exportTasks(): void
    {
        $user  = $this->requireAuth();
        $tasks = CalendarEvent::allTasksForUser($user['username']);

        $components = '';
        foreach ($tasks as $t) {
            $ical = CalendarEvent::buildICal(array_merge($t, ['type' => 'VTODO']));
            // strip outer VCALENDAR wrapper and keep just VTODO
            if (preg_match('/BEGIN:VTODO.*?END:VTODO/s', $ical, $m)) {
                $components .= $m[0] . "\r\n";
            }
        }

        $output = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//TwiStar Systems//Cardy//EN\r\n"
                . $components
                . "END:VCALENDAR\r\n";

        $filename = 'cardy-tasks-' . date('Y-m-d') . '.ics';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($output));
        echo $output;
        exit;
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function extractTaskFormData(): array
    {
        $post = $_POST;

        $timezone = trim($post['timezone'] ?? 'UTC');
        try { new \DateTimeZone($timezone); } catch (\Exception $e) { $timezone = 'UTC'; }

        $status = strtoupper(trim($post['status'] ?? 'NEEDS-ACTION'));
        if (!in_array($status, ['NEEDS-ACTION', 'IN-PROCESS', 'COMPLETED', 'CANCELLED'], true)) {
            $status = 'NEEDS-ACTION';
        }

        $priority = (int) ($post['priority'] ?? 0);
        if ($priority < 0 || $priority > 9) {
            $priority = 0;
        }

        $percentComplete = max(0, min(100, (int) ($post['percent_complete'] ?? 0)));
        // Auto-sync percent to status
        if ($status === 'COMPLETED') {
            $percentComplete = 100;
        } elseif ($status === 'NEEDS-ACTION' && $percentComplete === 100) {
            $percentComplete = 0;
        }

        $categories = trim($post['categories'] ?? '');

        $alarmMinutes = max(0, (int) ($post['alarm_minutes'] ?? 0));

        return [
            'type'             => 'VTODO',
            'summary'          => trim($post['summary'] ?? ''),
            'description'      => $post['description'] ?? '',
            'status'           => $status,
            'priority'         => $priority,
            'percent_complete' => $percentComplete,
            'due_date'         => $post['due_date'] ?? '',
            'due_time'         => $post['due_time'] ?? '',
            'categories'       => $categories,
            'color'            => preg_match('/^#[0-9a-fA-F]{6}$/', $post['color'] ?? '') ? $post['color'] : '',
            'visibility'       => in_array(strtoupper($post['visibility'] ?? 'PUBLIC'), ['PUBLIC', 'PRIVATE', 'CONFIDENTIAL']) ? strtoupper($post['visibility']) : 'PUBLIC',
            'timezone'         => $timezone,
            'alarm_minutes'    => $alarmMinutes,
            'uid'              => $post['uid'] ?? '',
            // Not used by VTODO but keep as empty to satisfy buildICal
            'start_date'       => '',
            'start_time'       => '',
            'end_date'         => '',
            'end_time'         => '',
            'all_day'          => false,
            'location'         => '',
            'organizer'        => ['name' => '', 'email' => ''],
            'attendees'        => [],
            'rrule'            => null,
        ];
    }
}
