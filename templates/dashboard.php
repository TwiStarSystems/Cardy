<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$davUrl = \Cardy\Config::get('app.dav_url', 'http://localhost');
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Welcome back, <?= $_ctrl->e($user['display_name'] ?: $user['username']) ?>!</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
    </div>
    <div class="stat-value"><?= (int) $contactCount ?></div>
    <div class="stat-label">Contacts</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>
    <div class="stat-value"><?= (int) $upcomingCount ?></div>
    <div class="stat-label">Upcoming Events</div>
  </div>
</div>

<!-- Quick actions -->
<div class="flex gap-sm mb-lg flex-wrap">
  <a href="/contacts/new" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    New Contact
  </a>
  <a href="/calendar/new" class="btn btn-secondary">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    New Event
  </a>
  <a href="/contacts" class="btn btn-ghost">View Contacts</a>
  <a href="/calendar" class="btn btn-ghost">View Calendar</a>
</div>

<!-- Upcoming events -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Upcoming Events</span>
    <a href="/calendar" class="btn btn-ghost btn-sm">View Calendar</a>
  </div>
  <?php if (empty($upcoming)): ?>
    <div class="empty-state" style="padding:var(--spacing-lg)">
      <div class="empty-state-icon">📅</div>
      <h3>No upcoming events</h3>
      <p class="text-muted">Add your first event to get started.</p>
      <a href="/calendar/new" class="btn btn-primary mt-sm">Add Event</a>
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Time</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcoming as $event): ?>
          <tr>
            <td><?= $_ctrl->e($event['summary'] ?: '(no title)') ?></td>
            <td><?= $_ctrl->e($event['start_date']) ?></td>
            <td><?= $event['all_day'] ? 'All day' : $_ctrl->e($event['start_time']) ?></td>
            <td>
              <a href="/calendar/<?= (int) $event['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- DAV connection info -->
<div class="dav-info mt-lg">
  <div class="dav-info-title">📡 DAV Client Connection Settings</div>
  <p class="text-sm text-muted mb-sm">Configure your CardDAV/CalDAV clients with these settings:</p>
  <div class="form-row">
    <div>
      <div class="form-label">CardDAV URL</div>
      <div class="dav-url"><?= $_ctrl->e($davUrl) ?>/addressbooks/<?= $_ctrl->e($user['username']) ?>/default/</div>
    </div>
    <div>
      <div class="form-label">CalDAV URL</div>
      <div class="dav-url"><?= $_ctrl->e($davUrl) ?>/calendars/<?= $_ctrl->e($user['username']) ?>/default/</div>
    </div>
  </div>
  <p class="text-xs text-muted mt-sm">Use your Cardy username and password to authenticate.</p>
</div>

<?php
$content  = ob_get_clean();
$pageTitle = 'Dashboard';
require __DIR__ . '/layout.php';
