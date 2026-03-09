<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$isEdit = ($event !== null);
$action = $isEdit ? '/calendar/' . (int) $event['id'] : '/calendar';
$e      = $event ?? [];
$defDate = $date ?? date('Y-m-d');
?>

<div class="page-header">
  <div>
    <a href="/calendar" class="text-muted text-sm">← Calendar</a>
    <h1 class="page-title" style="margin-top:4px"><?= $isEdit ? 'Edit Event' : 'New Event' ?></h1>
  </div>
  <a href="/calendar" class="btn btn-secondary">Cancel</a>
</div>

<div class="card">
<form method="POST" action="<?= $action ?>">
  <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
  <?php if ($isEdit): ?>
  <input type="hidden" name="uid" value="<?= $_ctrl->e($e['uid'] ?? '') ?>">
  <?php endif; ?>

  <div class="form-group">
    <label class="form-label" for="type">Event Type</label>
    <select class="form-control" id="type" name="type">
      <option value="VEVENT"   <?= ($e['type'] ?? 'VEVENT') === 'VEVENT'   ? 'selected' : '' ?>>Event</option>
      <option value="VTODO"    <?= ($e['type'] ?? '') === 'VTODO'    ? 'selected' : '' ?>>Task (To-Do)</option>
      <option value="VJOURNAL" <?= ($e['type'] ?? '') === 'VJOURNAL' ? 'selected' : '' ?>>Journal Entry</option>
    </select>
  </div>

  <div class="form-group">
    <label class="form-label" for="summary">Title <span style="color:var(--color-red)">*</span></label>
    <input class="form-control" type="text" id="summary" name="summary" required autofocus
           value="<?= $_ctrl->e($e['summary'] ?? '') ?>" placeholder="Event title">
  </div>

  <div class="form-group">
    <label class="form-check">
      <input type="checkbox" name="all_day" id="all_day" value="1"
             <?= !empty($e['all_day']) ? 'checked' : '' ?>
             onchange="toggleAllDay(this.checked)">
      All-day event
    </label>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="start_date">Start Date</label>
      <input class="form-control" type="date" id="start_date" name="start_date" required
             value="<?= $_ctrl->e($e['start_date'] ?? $defDate) ?>">
    </div>
    <div class="form-group" id="start_time_group" <?= !empty($e['all_day']) ? 'style="display:none"' : '' ?>>
      <label class="form-label" for="start_time">Start Time</label>
      <input class="form-control" type="time" id="start_time" name="start_time"
             value="<?= $_ctrl->e($e['start_time'] ?? '09:00') ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="end_date">End Date</label>
      <input class="form-control" type="date" id="end_date" name="end_date" required
             value="<?= $_ctrl->e($e['end_date'] ?? $defDate) ?>">
    </div>
    <div class="form-group" id="end_time_group" <?= !empty($e['all_day']) ? 'style="display:none"' : '' ?>>
      <label class="form-label" for="end_time">End Time</label>
      <input class="form-control" type="time" id="end_time" name="end_time"
             value="<?= $_ctrl->e($e['end_time'] ?? '10:00') ?>">
    </div>
  </div>

  <div class="form-group">
    <label class="form-label" for="location">Location</label>
    <input class="form-control" type="text" id="location" name="location"
           value="<?= $_ctrl->e($e['location'] ?? '') ?>" placeholder="Conference Room A, 123 Main St…">
  </div>

  <div class="form-group">
    <label class="form-label" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="4"
              placeholder="Event details…"><?= $_ctrl->e($e['description'] ?? '') ?></textarea>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <?= $isEdit ? 'Save Changes' : 'Create Event' ?>
    </button>
    <?php if ($isEdit): ?>
    <a href="/calendar" class="btn btn-secondary">Cancel</a>
    <form method="POST" action="/calendar/<?= (int) $e['id'] ?>/delete" style="display:inline;margin-left:auto">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <button type="submit" class="btn btn-danger"
              onclick="return confirm('Delete this event? This cannot be undone.')">
        Delete Event
      </button>
    </form>
    <?php endif; ?>
  </div>
</form>
</div>

<script>
function toggleAllDay(checked) {
  document.getElementById('start_time_group').style.display = checked ? 'none' : '';
  document.getElementById('end_time_group').style.display   = checked ? 'none' : '';
}
// Keep end_date in sync with start_date when blank
document.getElementById('start_date').addEventListener('change', function() {
  var endDate = document.getElementById('end_date');
  if (!endDate.value || endDate.value < this.value) { endDate.value = this.value; }
});
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $isEdit ? 'Edit Event' : 'New Event';
require __DIR__ . '/../../templates/layout.php';
