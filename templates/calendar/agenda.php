<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$todayStr = date('Y-m-d');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Calendar — Agenda</h1>
  </div>
  <a href="/calendar/new" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    New Event
  </a>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div style="display:flex;gap:6px;margin-bottom:var(--spacing-md);align-items:center;flex-wrap:wrap">
  <a href="/calendar" class="btn btn-ghost btn-sm">Month</a>
  <a href="/calendar/week" class="btn btn-ghost btn-sm">Week</a>
  <a href="/calendar/day?date=<?= $todayStr ?>" class="btn btn-ghost btn-sm">Day</a>
  <a href="/calendar/agenda" class="btn btn-secondary btn-sm" aria-current="page">Agenda</a>
  <span style="margin-left:auto;display:flex;gap:6px">
    <a href="/calendar/export" class="btn btn-ghost btn-sm">↓ Export .ics</a>
    <a href="/calendar/import" class="btn btn-ghost btn-sm">↑ Import .ics</a>
  </span>
</div>

<?php if (empty($grouped)): ?>
<div class="card" style="padding:var(--spacing-xl);text-align:center;color:var(--color-text-muted)">
  No upcoming events in the next 60 days.
</div>
<?php else: ?>

<?php foreach ($grouped as $date => $dayEvents): ?>
<?php
$dt      = new \DateTime($date);
$isToday = $date === $todayStr;
?>
<div class="card" style="margin-bottom:var(--spacing-md);overflow:hidden">
  <div class="card-header" style="<?= $isToday ? 'background:var(--color-sparkle-purple);color:#fff;' : 'background:var(--color-bg-subtle);' ?>display:flex;align-items:center;justify-content:space-between">
    <span class="card-title"><?= $isToday ? 'Today — ' : '' ?><?= $dt->format('l, F j, Y') ?></span>
    <a href="/calendar/day?date=<?= $date ?>" class="btn btn-ghost btn-sm" style="<?= $isToday ? 'color:#fff' : '' ?>">Day view</a>
  </div>
  <div class="table-wrapper">
    <table>
      <tbody>
        <?php foreach ($dayEvents as $ev): ?>
        <tr>
          <td style="width:90px;white-space:nowrap">
            <?php if ($ev['all_day']): ?>
            <span class="badge badge-purple">All day</span>
            <?php else: ?>
            <?= $_ctrl->e($ev['start_time']) ?>
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
<?php endforeach; ?>

<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle = 'Calendar — Agenda';
require __DIR__ . '/../../templates/layout.php';
