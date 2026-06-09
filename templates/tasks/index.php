<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$today = date('Y-m-d');

// Priority helpers
$priorityLabel = static function (int $p): string {
    return match (true) {
        $p >= 1 && $p <= 4 => 'High',
        $p === 5            => 'Medium',
        $p >= 6 && $p <= 9 => 'Low',
        default             => '',
    };
};

$priorityColor = static function (int $p): string {
    return match (true) {
        $p >= 1 && $p <= 4 => '#e53e3e',
        $p === 5            => '#d97706',
        $p >= 6 && $p <= 9 => '#38a169',
        default             => 'var(--color-text-muted)',
    };
};
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Tasks</h1>
    <p class="text-muted text-sm">To-dos synced via CalDAV</p>
  </div>
  <div class="flex gap-sm">
    <a href="/tasks/export" class="btn btn-ghost btn-sm">↓ Export .ics</a>
    <a href="/tasks/new" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      New Task
    </a>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex;gap:4px;margin-bottom:var(--spacing-md);border-bottom:1px solid var(--color-border);padding-bottom:0">
  <?php foreach ([
      'all'       => 'All',
      'active'    => 'Active',
      'completed' => 'Completed',
      'cancelled' => 'Cancelled',
  ] as $key => $label): ?>
  <a href="/tasks?filter=<?= $key ?>"
     class="btn btn-ghost btn-sm"
     style="border-radius:var(--radius) var(--radius) 0 0;border-bottom:<?= $filter === $key ? '2px solid var(--color-sparkle-purple)' : '2px solid transparent' ?>;font-weight:<?= $filter === $key ? '600' : '400' ?>">
    <?= $label ?>
    <?php if ($counts[$key] > 0): ?>
    <span class="badge badge-muted" style="margin-left:4px"><?= $counts[$key] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if (empty($tasks)): ?>
<div class="card" style="padding:var(--spacing-xl);text-align:center;color:var(--color-text-muted)">
  <?php if ($filter === 'all'): ?>
  <p style="font-size:var(--text-lg);margin-bottom:var(--spacing-sm)">No tasks yet.</p>
  <a href="/tasks/new" class="btn btn-primary">Create your first task</a>
  <?php else: ?>
  <p>No <?= $_ctrl->e($filter) ?> tasks.</p>
  <?php endif; ?>
</div>
<?php else: ?>

<div class="card" style="overflow:hidden">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th style="width:36px"></th>
          <th style="width:72px">Priority</th>
          <th>Title</th>
          <th style="width:130px">Due</th>
          <th style="width:80px">Progress</th>
          <th style="width:140px">Calendar</th>
          <th style="width:100px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tasks as $t):
            $status     = strtoupper($t['status'] ?? 'NEEDS-ACTION');
            $isComplete = $status === 'COMPLETED';
            $isCancelled= $status === 'CANCELLED';
            $inProgress = $status === 'IN-PROCESS';
            $pLabel     = $priorityLabel((int) ($t['priority'] ?? 0));
            $pColor     = $priorityColor((int) ($t['priority'] ?? 0));
            $dueDate    = $t['due_date'] ?? '';
            $isOverdue  = $dueDate && !$isComplete && !$isCancelled && $dueDate < $today;
            $isDueToday = $dueDate && !$isComplete && !$isCancelled && $dueDate === $today;
            $pct        = (int) ($t['percent_complete'] ?? 0);
            $rowStyle   = $isComplete || $isCancelled ? 'opacity:.65' : '';
        ?>
        <tr style="<?= $rowStyle ?>">
          <!-- Completion toggle -->
          <td>
            <form method="POST" action="/tasks/<?= (int) $t['id'] ?>/toggle" style="margin:0">
              <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
              <button type="submit" class="btn btn-ghost" style="padding:4px;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center"
                      title="<?= $isComplete ? 'Mark as incomplete' : 'Mark as complete' ?>">
                <?php if ($isComplete): ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;color:var(--color-green,#38a169)">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;color:var(--color-text-muted)">
                  <circle cx="12" cy="12" r="9"/>
                </svg>
                <?php endif; ?>
              </button>
            </form>
          </td>

          <!-- Priority -->
          <td>
            <?php if ($pLabel !== ''): ?>
            <span style="font-size:var(--text-xs);font-weight:600;color:<?= $pColor ?>"><?= $pLabel ?></span>
            <?php else: ?>
            <span class="text-muted" style="font-size:var(--text-xs)">—</span>
            <?php endif; ?>
          </td>

          <!-- Title + status badges -->
          <td>
            <a href="/tasks/<?= (int) $t['id'] ?>/edit"
               style="font-weight:500;<?= $isComplete ? 'text-decoration:line-through;color:var(--color-text-muted)' : '' ?>">
              <?= $_ctrl->e($t['summary'] ?: '(no title)') ?>
            </a>
            <?php if ($inProgress): ?>
            <span class="badge badge-purple" style="margin-left:6px">In Progress</span>
            <?php endif; ?>
            <?php if ($isCancelled): ?>
            <span class="badge badge-muted" style="margin-left:6px">Cancelled</span>
            <?php endif; ?>
            <?php if (!empty($t['categories'])): ?>
            <span class="text-muted text-xs" style="display:block;margin-top:2px">
              <?= $_ctrl->e(is_array($t['categories']) ? implode(', ', $t['categories']) : $t['categories']) ?>
            </span>
            <?php endif; ?>
          </td>

          <!-- Due date -->
          <td style="white-space:nowrap">
            <?php if ($dueDate): ?>
            <span style="font-size:var(--text-sm);color:<?= $isOverdue ? '#e53e3e' : ($isDueToday ? '#d97706' : 'inherit') ?>;font-weight:<?= ($isOverdue || $isDueToday) ? '600' : '400' ?>">
              <?= $_ctrl->e(date('M j, Y', strtotime($dueDate))) ?>
              <?php if ($isOverdue): ?><span style="font-size:var(--text-xs)"> Overdue</span>
              <?php elseif ($isDueToday): ?><span style="font-size:var(--text-xs)"> Today</span>
              <?php endif; ?>
              <?php if ($t['due_time'] && $t['due_time'] !== '00:00'): ?>
              <br><span class="text-muted text-xs"><?= $_ctrl->e($t['due_time']) ?></span>
              <?php endif; ?>
            </span>
            <?php else: ?>
            <span class="text-muted" style="font-size:var(--text-xs)">No due date</span>
            <?php endif; ?>
          </td>

          <!-- Progress -->
          <td style="white-space:nowrap">
            <?php if ($pct > 0 || $inProgress): ?>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="flex:1;height:6px;background:var(--color-border);border-radius:3px;min-width:50px">
                <div style="width:<?= $pct ?>%;height:100%;background:var(--color-sparkle-purple);border-radius:3px"></div>
              </div>
              <span style="font-size:var(--text-xs);color:var(--color-text-muted)"><?= $pct ?>%</span>
            </div>
            <?php else: ?>
            <span class="text-muted" style="font-size:var(--text-xs)">—</span>
            <?php endif; ?>
          </td>

          <!-- Calendar -->
          <td>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:var(--text-xs);color:var(--color-text-muted)">
              <?php if ($t['calendar_color'] ?? ''): ?>
              <span style="width:8px;height:8px;border-radius:50%;background:<?= $_ctrl->e($t['calendar_color']) ?>;flex-shrink:0;display:inline-block"></span>
              <?php endif; ?>
              <?= $_ctrl->e($t['calendar_name'] ?? 'Calendar') ?>
            </span>
          </td>

          <!-- Actions -->
          <td>
            <div style="display:flex;gap:4px">
              <a href="/tasks/<?= (int) $t['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
              <form method="POST" action="/tasks/<?= (int) $t['id'] ?>/delete" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
                <button type="submit" class="btn btn-ghost btn-sm text-danger"
                        onclick="return confirm('Delete task \'<?= addslashes($_ctrl->e($t['summary'] ?: '(no title)')) ?>\'?')">
                  Delete
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php
$content  = ob_get_clean();
$pageTitle = 'Tasks';
require __DIR__ . '/../layout.php';
?>
