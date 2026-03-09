<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\Contact;
use Cardy\Models\CalendarEvent;
use Cardy\WebUI\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $contactCount  = Contact::countForUser($user['username']);
        $upcomingCount = CalendarEvent::countUpcomingForUser($user['username']);

        // Next 5 upcoming events
        $upcoming = array_slice(
            CalendarEvent::allForUser($user['username'], (int) date('Y'), (int) date('m')),
            0,
            5
        );

        // Also get next month if fewer than 5
        if (count($upcoming) < 5) {
            $nextMonth = (int) date('m') + 1;
            $nextYear  = (int) date('Y');
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            $next = CalendarEvent::allForUser($user['username'], $nextYear, $nextMonth);
            $upcoming = array_merge($upcoming, $next);
            $upcoming = array_slice($upcoming, 0, 5);
        }

        $this->render('dashboard', [
            'user'          => $user,
            'contactCount'  => $contactCount,
            'upcomingCount' => $upcomingCount,
            'upcoming'      => $upcoming,
            'csrf'          => $this->csrfToken(),
        ]);
    }
}
