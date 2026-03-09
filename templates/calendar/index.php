<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();

// Build calendar grid
$firstDay    = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int) date('t', $firstDay);
$startDow    = (int) date('N', $firstDay); // 1=Mon … 7=Sun → convert to 0-based Sun-start
$startDow    = $startDow % 7; // Sun=0, Mon=1 …

$prevMonth = $month - 1;
$prevYear  = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear  = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$today     = (int) date('j');
$thisYear  = (int) date('Y');
$thisMonth = (int) date('n');

$monthName = date('F', $firstDay);
$weekdays  = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Calendar</h1>
  </div>
  <a href="/calendar/new?date=<?= $year ?>-<?= str_pad((string) $month, 2, '0', STR_PAD_LEFT) ?>-01" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    New Event
  </a>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<!-- Navigation -->
<div class="calendar-nav">
  <a href="/calendar?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-secondary btn-sm">← Previous</a>
  <span class="calendar-month-title"><?= $_ctrl->e($monthName) ?> <?= $year ?></span>
  <a href="/calendar?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn btn-secondary btn-sm">Next →</a>
</div>

<!-- Grid -->
<div class="calendar-grid">
  <div class="calendar-weekdays">
    <?php foreach ($weekdays as $wd): ?>
    <div class="calendar-weekday"><?= $wd ?></div>
    <?php endforeach; ?>
  </div>

  <div class="calendar-days">
    <?php
    // Leading empty cells
    for ($i = 0; $i < $startDow; $i++):
    ?>
    <div class="calendar-day empty"></div>
    <?php endfor; ?>

    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
    <?php
    $isToday    = ($day === $today && $month === $thisMonth && $year === $thisYear);
    $dayEvents  = $eventMap[$day] ?? [];
    $classes    = 'calendar-day';
    if ($isToday) { $classes .= ' today'; }
    if (!empty($dayEvents)) { $classes .= ' has-events'; }
    ?>
    <div class="<?= $classes ?>">
      <a href="/calendar/new?date=<?= $year ?>-<?= str_pad((string) $month, 2, '0', STR_PAD_LEFT) ?>-<?= str_pad((string) $day, 2, '0', STR_PAD_LEFT) ?>"
         class="day-number" title="Add event on <?= $monthName ?> <?= $day ?>">
        <?= $day ?>
      </a>
      <?php foreach (array_slice($dayEvents, 0, 3) as $ev): ?>
      <a href="/calendar/<?= (int) $ev['id'] ?>/edit" class="calendar-event-pill" title="<?= $_ctrl->e($ev['summary']) ?>">
        <?= $_ctrl->e($ev['summary'] ?: '(no title)') ?>
      </a>
      <?php endforeach; ?>
      <?php if (count($dayEvents) > 3): ?>
      <span class="text-xs text-muted">+<?= count($dayEvents) - 3 ?> more</span>
      <?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- Event list below calendar -->
<?php if (!empty($events)): ?>
<div class="card mt-lg">
  <div class="card-header">
    <span class="card-title">Events in <?= $_ctrl->e($monthName) ?> <?= $year ?></span>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Event</th>
          <th>Date</th>
          <th>Time</th>
          <th>Location</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $ev): ?>
        <tr>
          <td><?= $_ctrl->e($ev['summary'] ?: '(no title)') ?></td>
          <td><?= $_ctrl->e($ev['start_date']) ?></td>
          <td><?= $ev['all_day'] ? '<span class="badge badge-purple">All day</span>' : $_ctrl->e($ev['start_time']) ?></td>
          <td><?= $_ctrl->e($ev['location']) ?></td>
          <td>
            <a href="/calendar/<?= (int) $ev['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
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
$pageTitle = "Calendar — {$monthName} {$year}";
require __DIR__ . '/../../templates/layout.php';
