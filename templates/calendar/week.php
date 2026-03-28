<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$todayStr  = date('Y-m-d');
$weekLabel = $weekStart->format('M j') . ' – ' . $weekEnd->format('M j, Y');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Calendar</h1>
  </div>
  <a href="/calendar/new?date=<?= $todayStr ?>" class="btn btn-primary">
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
  <a href="/calendar/week?date=<?= $prevDate ?>" class="btn btn-secondary btn-sm">← Prev Week</a>
  <a href="/calendar/week?date=<?= $todayStr ?>" class="btn btn-secondary btn-sm">Today</a>
  <span class="calendar-month-title"><?= $_ctrl->e($weekLabel) ?></span>
  <a href="/calendar/week?date=<?= $nextDate ?>" class="btn btn-secondary btn-sm">Next Week →</a>
</div>

<div style="display:flex;gap:6px;margin-bottom:var(--spacing-md);align-items:center;flex-wrap:wrap">
  <a href="/calendar" class="btn btn-ghost btn-sm">Month</a>
  <a href="/calendar/week" class="btn btn-secondary btn-sm" aria-current="page">Week</a>
  <a href="/calendar/day?date=<?= $todayStr ?>" class="btn btn-ghost btn-sm">Day</a>
  <a href="/calendar/agenda" class="btn btn-ghost btn-sm">Agenda</a>
  <span style="margin-left:auto;display:flex;gap:6px">
    <a href="/calendar/export" class="btn btn-ghost btn-sm">↓ Export .ics</a>
    <a href="/calendar/import" class="btn btn-ghost btn-sm">↑ Import .ics</a>
  </span>
</div>

<!-- Week grid -->
<div class="card" style="overflow:hidden">
  <div style="display:grid;grid-template-columns:repeat(7,1fr)">
    <?php foreach ($days as $d): ?>
    <div style="padding:8px 10px;text-align:center;font-size:var(--text-sm);font-weight:600;
                border-bottom:1px solid var(--color-border);
                background:<?= $d['isToday'] ? 'var(--color-sparkle-purple)' : 'var(--color-bg-subtle)' ?>;
                color:<?= $d['isToday'] ? '#fff' : 'var(--color-text-muted)' ?>">
      <a href="/calendar/day?date=<?= $d['dateKey'] ?>"
         style="text-decoration:none;color:inherit;display:block">
        <?= $d['date']->format('D') ?><br>
        <span style="font-size:var(--text-lg);font-weight:700"><?= $d['date']->format('j') ?></span>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr);min-height:280px;align-items:start">
    <?php foreach ($days as $d): ?>
    <div style="border-right:1px solid var(--color-border);padding:6px;min-height:200px">
      <?php if (empty($d['events'])): ?>
      <a href="/calendar/new?date=<?= $d['dateKey'] ?>" class="text-xs text-muted"
         style="opacity:.4;font-size:var(--text-xs)">+ Add</a>
      <?php endif; ?>
      <?php foreach ($d['events'] as $ev): ?>
      <?php if ($ev['id'] !== null): ?>
      <a href="/calendar/<?= (int) $ev['id'] ?>/edit"
         class="calendar-event-pill" style="display:block;margin-bottom:3px"
         title="<?= $_ctrl->e($ev['summary']) ?>">
        <?= $_ctrl->e($ev['all_day'] ? $ev['summary'] : ($ev['start_time'] . ' ' . $ev['summary'])) ?>
      </a>
      <?php else: ?>
      <span class="calendar-event-pill" style="display:block;margin-bottom:3px;cursor:default;opacity:.85"
            title="<?= $_ctrl->e($ev['summary']) ?>">
        <?= $_ctrl->e($ev['summary']) ?>
      </span>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'Calendar — Week of ' . $weekStart->format('M j');
require __DIR__ . '/../../templates/layout.php';
