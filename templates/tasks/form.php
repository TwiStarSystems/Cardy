<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$isEdit = ($task !== null);
$action = $isEdit ? '/tasks/' . (int) $task['id'] : '/tasks';
$t      = $task ?? [];
?>

<div class="page-header">
  <div>
    <a href="/tasks" class="text-muted text-sm">← Tasks</a>
    <h1 class="page-title" style="margin-top:4px"><?= $isEdit ? 'Edit Task' : 'New Task' ?></h1>
  </div>
  <a href="/tasks" class="btn btn-secondary">Cancel</a>
</div>

<div class="card">
<form method="POST" action="<?= $action ?>">
  <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
  <?php if ($isEdit): ?>
  <input type="hidden" name="uid" value="<?= $_ctrl->e($t['uid'] ?? '') ?>">
  <?php endif; ?>

  <!-- Title -->
  <div class="form-group">
    <label class="form-label" for="summary">Title <span style="color:var(--color-red)">*</span></label>
    <input class="form-control" type="text" id="summary" name="summary" required autofocus
           value="<?= $_ctrl->e($t['summary'] ?? '') ?>" placeholder="Task title">
  </div>

  <!-- Status + Priority row -->
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="status">Status</label>
      <select class="form-control" id="status" name="status" onchange="syncPercent(this.value)">
        <?php foreach ([
            'NEEDS-ACTION' => 'Needs Action',
            'IN-PROCESS'   => 'In Progress',
            'COMPLETED'    => 'Completed',
            'CANCELLED'    => 'Cancelled',
        ] as $val => $label): ?>
        <option value="<?= $val ?>"
          <?= strtoupper($t['status'] ?? 'NEEDS-ACTION') === $val ? 'selected' : '' ?>>
          <?= $label ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label" for="priority">Priority</label>
      <select class="form-control" id="priority" name="priority">
        <?php foreach ([
            0 => 'None',
            1 => 'High (1)',
            5 => 'Medium (5)',
            9 => 'Low (9)',
        ] as $val => $label): ?>
        <option value="<?= $val ?>"
          <?= (int) ($t['priority'] ?? 0) === $val ? 'selected' : '' ?>>
          <?= $label ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Due date + time -->
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="due_date">Due Date</label>
      <input class="form-control" type="date" id="due_date" name="due_date"
             value="<?= $_ctrl->e($t['due_date'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="due_time">Due Time <span class="text-muted text-xs">(optional)</span></label>
      <input class="form-control" type="time" id="due_time" name="due_time"
             value="<?= $_ctrl->e($t['due_time'] ?? '') ?>">
    </div>
  </div>

  <!-- Progress -->
  <div class="form-group">
    <label class="form-label" for="percent_complete">
      Progress: <span id="pct_display"><?= (int) ($t['percent_complete'] ?? 0) ?></span>%
    </label>
    <input type="range" id="percent_complete" name="percent_complete"
           min="0" max="100" step="5"
           value="<?= (int) ($t['percent_complete'] ?? 0) ?>"
           oninput="document.getElementById('pct_display').textContent = this.value"
           style="width:100%;accent-color:var(--color-sparkle-purple)">
    <div style="display:flex;justify-content:space-between;font-size:var(--text-xs);color:var(--color-text-muted)">
      <span>0%</span><span>50%</span><span>100%</span>
    </div>
  </div>

  <!-- Description -->
  <div class="form-group">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="4"
              placeholder="Task notes or details..."><?= $_ctrl->e($t['description'] ?? '') ?></textarea>
  </div>

  <!-- Categories -->
  <div class="form-group">
    <label class="form-label" for="categories">Categories <span class="text-muted text-xs">(comma-separated)</span></label>
    <input class="form-control" type="text" id="categories" name="categories"
           value="<?= $_ctrl->e(is_array($t['categories'] ?? null) ? implode(', ', $t['categories']) : ($t['categories'] ?? '')) ?>"
           placeholder="Work, Personal, Errand">
  </div>

  <!-- Reminder -->
  <div class="form-group">
    <label class="form-label" for="alarm_minutes">Reminder</label>
    <select class="form-control" id="alarm_minutes" name="alarm_minutes">
      <?php foreach ([
          0   => 'None',
          5   => '5 minutes before',
          10  => '10 minutes before',
          15  => '15 minutes before',
          30  => '30 minutes before',
          60  => '1 hour before',
          120 => '2 hours before',
          1440 => '1 day before',
      ] as $val => $label): ?>
      <option value="<?= $val ?>"
        <?= (int) ($t['alarm_minutes'] ?? 0) === $val ? 'selected' : '' ?>>
        <?= $label ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Calendar selector -->
  <?php if (count($allCalendars) > 1): ?>
  <div class="form-group">
    <label class="form-label" for="calendar_id">Calendar</label>
    <select class="form-control" id="calendar_id" name="calendar_id">
      <?php foreach ($allCalendars as $cal): ?>
      <option value="<?= (int) $cal['calendarid'] ?>"
        <?= (int) $cal['calendarid'] === (int) $activeCalId ? 'selected' : '' ?>>
        <?= $_ctrl->e($cal['displayname']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php else: ?>
  <input type="hidden" name="calendar_id" value="<?= (int) ($allCalendars[0]['calendarid'] ?? 0) ?>">
  <?php endif; ?>

  <!-- Advanced row: Timezone, Visibility, Color -->
  <details style="margin-bottom:var(--spacing-md)">
    <summary style="cursor:pointer;color:var(--color-text-muted);font-size:var(--text-sm);user-select:none">Advanced options</summary>
    <div style="margin-top:var(--spacing-md)">

      <div class="form-group">
        <label class="form-label" for="timezone">Timezone</label>
        <input class="form-control" type="text" id="timezone" name="timezone"
               list="tz-datalist" value="<?= $_ctrl->e($t['timezone'] ?? 'UTC') ?>"
               placeholder="UTC">
        <datalist id="tz-datalist">
          <?php foreach (\DateTimeZone::listIdentifiers() as $tz): ?>
          <option value="<?= $tz ?>">
          <?php endforeach; ?>
        </datalist>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="visibility">Visibility</label>
          <select class="form-control" id="visibility" name="visibility">
            <option value="PUBLIC"       <?= ($t['visibility'] ?? 'PUBLIC') === 'PUBLIC'       ? 'selected' : '' ?>>Public</option>
            <option value="PRIVATE"      <?= ($t['visibility'] ?? '') === 'PRIVATE'      ? 'selected' : '' ?>>Private</option>
            <option value="CONFIDENTIAL" <?= ($t['visibility'] ?? '') === 'CONFIDENTIAL' ? 'selected' : '' ?>>Confidential</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="color">Color</label>
          <input class="form-control" type="color" id="color" name="color"
                 value="<?= $_ctrl->e($t['color'] ?: '#9600E1') ?>"
                 style="height:38px;padding:2px 4px;cursor:pointer">
        </div>
      </div>

    </div>
  </details>

  <div style="display:flex;gap:var(--spacing-sm)">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Task' ?></button>
    <a href="/tasks" class="btn btn-secondary">Cancel</a>
    <?php if ($isEdit): ?>
    <form method="POST" action="/tasks/<?= (int) $task['id'] ?>/delete" style="margin:0;margin-left:auto">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <button type="submit" class="btn btn-secondary text-danger"
              onclick="return confirm('Delete this task permanently?')">Delete</button>
    </form>
    <?php endif; ?>
  </div>
</form>
</div>

<script nonce="<?= $_ctrl->nonce() ?>">
function syncPercent(statusVal) {
    const pct = document.getElementById('percent_complete');
    const display = document.getElementById('pct_display');
    if (statusVal === 'COMPLETED') {
        pct.value = 100;
        display.textContent = '100';
    } else if (statusVal === 'NEEDS-ACTION' && parseInt(pct.value) === 100) {
        pct.value = 0;
        display.textContent = '0';
    }
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = ($task ? ('Edit: ' . ($task['summary'] ?: 'Task')) : 'New Task');
require __DIR__ . '/../layout.php';
?>
