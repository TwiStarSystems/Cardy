<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$todayStr = date('Y-m-d');
$dayLabel = $day->format('l, F j, Y');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Calendar</h1>
  </div>
  <a href="/calendar/new?date=<?= $_ctrl->e($dateStr) ?>" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    New Event
  </a>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="calendar-nav">
  <a href="/calendar/day?date=<?= $prevDate ?>" class="btn btn-secondary btn-sm">← Prev Day</a>
  <a href="/calendar/day?date=<?= $todayStr ?>" class="btn btn-secondary btn-sm">Today</a>
  <span class="calendar-month-title"><?= $_ctrl->e($dayLabel) ?></span>
  <a href="/calendar/day?date=<?= $nextDate ?>" class="btn btn-secondary btn-sm">Next Day →</a>
</div>

<div style="display:flex;gap:6px;margin-bottom:var(--spacing-md);align-items:center;flex-wrap:wrap">
  <a href="/calendar" class="btn btn-ghost btn-sm">Month</a>
  <a href="/calendar/week?date=<?= $_ctrl->e($dateStr) ?>" class="btn btn-ghost btn-sm">Week</a>
  <a href="/calendar/day?date=<?= $_ctrl->e($dateStr) ?>" class="btn btn-secondary btn-sm" aria-current="page">Day</a>
  <a href="/calendar/agenda" class="btn btn-ghost btn-sm">Agenda</a>
  <span style="margin-left:auto;display:flex;gap:6px">
    <a href="/calendar/export" class="btn btn-ghost btn-sm">↓ Export .ics</a>
    <a href="/calendar/import" class="btn btn-ghost btn-sm">↑ Import .ics</a>
  </span>
</div>

<?php if (empty($events)): ?>
<div class="card" style="padding:var(--spacing-xl);text-align:center;color:var(--color-text-muted)">
  No events on this day. <a href="/calendar/new?date=<?= $_ctrl->e($dateStr) ?>">Add one?</a>
</div>
<?php else: ?>
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th style="width:110px">Time</th>
          <th>Event</th>
          <th>Location</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $ev): ?>
        <tr>
          <td style="white-space:nowrap">
            <?php if ($ev['all_day']): ?>
            <span class="badge badge-purple">All day</span>
            <?php else: ?>
            <?= $_ctrl->e($ev['start_time']) ?>
            <?php if ($ev['end_time'] && $ev['end_time'] !== $ev['start_time']): ?>
            <span class="text-muted"> – <?= $_ctrl->e($ev['end_time']) ?></span>
            <?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <?= $_ctrl->e($ev['summary'] ?: '(no title)') ?>
            <?php if (!empty($ev['rrule'])): ?><span class="badge badge-muted" style="margin-left:4px">Recurring</span><?php endif; ?>
          </td>
          <td class="text-muted"><?= $_ctrl->e($ev['location'] ?? '') ?></td>
          <td>
            <?php if ($ev['id'] !== null): ?>
            <a href="/calendar/<?= (int) $ev['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle = 'Calendar — ' . $day->format('M j, Y');
require __DIR__ . '/../../templates/layout.php';
