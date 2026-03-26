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
  <div class="flex gap-sm flex-wrap">
    <?php /* Calendar Switcher */ ?>
    <div class="dropdown" id="cal-switcher-dropdown">
      <button type="button" class="btn btn-secondary" id="cal-switcher-btn" title="Switch calendar">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <?php if ($activeCal && $activeCal['calendarcolor']): ?>
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $_ctrl->e($activeCal['calendarcolor']) ?>;display:inline-block;flex-shrink:0"></span>
        <?php endif; ?>
        <?= $_ctrl->e($activeCal['displayname'] ?? 'Calendar') ?>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;margin-left:2px">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>
      <div class="dropdown-menu" id="cal-switcher-menu" style="min-width:280px">
        <div style="padding:8px 12px 4px;font-size:var(--text-xs);color:var(--color-text-muted);user-select:all" title="DAV URL for this calendar">
          DAV: /calendars/<?= $_ctrl->e($user['username']) ?>/<?= $_ctrl->e($activeCal['uri'] ?? 'default') ?>/
        </div>
        <?php if (count($allCalendars) > 1): ?>
        <div class="dropdown-divider"></div>
        <?php foreach ($allCalendars as $cal): ?>
          <?php if ((int)$cal['calendarid'] !== (int)($activeCalId ?? 0)): ?>
          <form method="POST" action="/calendar/calendars/<?= (int)$cal['calendarid'] ?>/switch" style="display:contents">
            <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
            <button type="submit" class="dropdown-item" style="display:flex;align-items:center;gap:8px">
              <?php if ($cal['calendarcolor']): ?>
              <span style="width:10px;height:10px;border-radius:50%;background:<?= $_ctrl->e($cal['calendarcolor']) ?>;display:inline-block;flex-shrink:0"></span>
              <?php endif; ?>
              <?= $_ctrl->e($cal['displayname']) ?>
            </button>
          </form>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
        <div class="dropdown-divider"></div>
        <?php /* Create new calendar */ ?>
        <button type="button" class="dropdown-item" id="cal-create-toggle" style="display:flex;align-items:center;gap:6px">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          New Calendar
        </button>
        <div id="cal-create-form" style="display:none;padding:8px 12px">
          <form method="POST" action="/calendar/calendars">
            <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
            <input type="text" name="displayname" class="form-control" placeholder="e.g. Work Calendar" required
                   style="margin-bottom:6px;font-size:var(--text-sm)" maxlength="100">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
              <label style="font-size:var(--text-sm);flex-shrink:0">Color:</label>
              <input type="color" name="color" value="#9600E1" style="width:40px;height:28px;border:none;cursor:pointer;background:none">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Create</button>
          </form>
        </div>
        <?php /* Rename current calendar */ ?>
        <button type="button" class="dropdown-item" id="cal-rename-toggle" style="display:flex;align-items:center;gap:6px">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          Rename / Recolor...
        </button>
        <div id="cal-rename-form" style="display:none;padding:8px 12px">
          <form method="POST" action="/calendar/calendars/<?= (int)($activeCalId ?? 0) ?>/rename">
            <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
            <input type="text" name="displayname" class="form-control"
                   value="<?= $_ctrl->e($activeCal['displayname'] ?? '') ?>"
                   placeholder="New name..." required
                   style="margin-bottom:6px;font-size:var(--text-sm)" maxlength="100">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
              <label style="font-size:var(--text-sm);flex-shrink:0">Color:</label>
              <input type="color" name="color" value="<?= $_ctrl->e($activeCal['calendarcolor'] ?? '#9600E1') ?>"
                     style="width:40px;height:28px;border:none;cursor:pointer;background:none">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save</button>
          </form>
        </div>
        <?php if (count($allCalendars) > 1): ?>
        <div class="dropdown-divider"></div>
        <form method="POST" action="/calendar/calendars/<?= (int)($activeCalId ?? 0) ?>/delete" style="display:contents">
          <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
          <button type="submit" class="dropdown-item text-danger"
                  onclick="return confirm('Delete calendar \"<?= $_ctrl->e($activeCal['displayname'] ?? 'this calendar') ?>\"?\n\nAll events in it will be permanently deleted.')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0;color:var(--color-danger,#e53e3e)">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Delete This Calendar
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div><!-- end cal-switcher-dropdown -->

    <a href="/calendar/new?date=<?= $year ?>-<?= str_pad((string) $month, 2, '0', STR_PAD_LEFT) ?>-01" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      New Event
    </a>
  </div>
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

<script>
(function () {
  function initDropdown(btnId, menuId) {
    var btn  = document.getElementById(btnId);
    var menu = document.getElementById(menuId);
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', function () { menu.style.display = 'none'; });
  }
  function togglePanel(panelId) {
    var el = document.getElementById(panelId);
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
  initDropdown('cal-switcher-btn', 'cal-switcher-menu');
  var createToggle = document.getElementById('cal-create-toggle');
  var renameToggle = document.getElementById('cal-rename-toggle');
  if (createToggle) createToggle.addEventListener('click', function (e) { e.stopPropagation(); togglePanel('cal-create-form'); });
  if (renameToggle) renameToggle.addEventListener('click', function (e) { e.stopPropagation(); togglePanel('cal-rename-form'); });
})();
</script>

<?php
$content   = ob_get_clean();
$pageTitle = "Calendar — {$monthName} {$year}";
require __DIR__ . '/../../templates/layout.php';
