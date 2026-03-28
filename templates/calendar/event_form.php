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

  <div class="form-group" id="timezone_group" <?= !empty($e['all_day']) ? 'style="display:none"' : '' ?>>
    <label class="form-label" for="timezone">Timezone</label>
    <input class="form-control" type="text" id="timezone" name="timezone"
           list="tz-datalist" value="<?= $_ctrl->e($e['timezone'] ?? 'UTC') ?>"
           placeholder="UTC">
    <datalist id="tz-datalist">
      <?php foreach (\DateTimeZone::listIdentifiers() as $tz): ?>
      <option value="<?= $tz ?>">
      <?php endforeach; ?>
    </datalist>
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

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="status">Status</label>
      <select class="form-control" id="status" name="status">
        <option value=""          <?= ($e['status'] ?? '') === ''           ? 'selected' : '' ?>>— Not set —</option>
        <option value="CONFIRMED" <?= ($e['status'] ?? '') === 'CONFIRMED'  ? 'selected' : '' ?>>Confirmed</option>
        <option value="TENTATIVE" <?= ($e['status'] ?? '') === 'TENTATIVE'  ? 'selected' : '' ?>>Tentative</option>
        <option value="CANCELLED" <?= ($e['status'] ?? '') === 'CANCELLED'  ? 'selected' : '' ?>>Cancelled</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label" for="visibility">Visibility</label>
      <select class="form-control" id="visibility" name="visibility">
        <option value="PUBLIC"       <?= ($e['visibility'] ?? 'PUBLIC') === 'PUBLIC'       ? 'selected' : '' ?>>Public</option>
        <option value="PRIVATE"      <?= ($e['visibility'] ?? '') === 'PRIVATE'      ? 'selected' : '' ?>>Private</option>
        <option value="CONFIDENTIAL" <?= ($e['visibility'] ?? '') === 'CONFIDENTIAL' ? 'selected' : '' ?>>Confidential</option>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group" style="flex:4">
      <label class="form-label" for="categories">Categories / Tags</label>
      <input class="form-control" type="text" id="categories" name="categories"
             value="<?= $_ctrl->e(is_array($e['categories'] ?? '') ? implode(', ', $e['categories']) : ($e['categories'] ?? '')) ?>"
             placeholder="Work, Important, Personal…">
      <span class="form-hint">Comma-separated</span>
    </div>
    <div class="form-group" style="flex:1">
      <label class="form-label" for="color">Event Color</label>
      <input class="form-control" type="color" id="color" name="color"
             value="<?= $_ctrl->e(!empty($e['color']) ? $e['color'] : '#9600E1') ?>">
    </div>
  </div>

  <!-- Organizer -->
  <div class="form-section-title" style="margin-top:var(--spacing-md)">Organizer</div>
  <div class="form-row">
    <div class="form-group" style="flex:1">
      <label class="form-label">Name</label>
      <input class="form-control" type="text" name="organizer_name" list="contacts-name-list"
             value="<?= $_ctrl->e($e['organizer']['name'] ?? '') ?>" placeholder="Organizer name">
    </div>
    <div class="form-group" style="flex:2">
      <label class="form-label">Email</label>
      <input class="form-control" type="email" name="organizer_email" list="contacts-email-list"
             value="<?= $_ctrl->e($e['organizer']['email'] ?? '') ?>" placeholder="organizer@example.com">
    </div>
  </div>

  <!-- Attendees -->
  <div class="form-section-title" style="margin-top:var(--spacing-md)">Attendees</div>
  <div id="attendee-list">
    <?php foreach (($e['attendees'] ?? []) as $att): ?>
    <div class="form-row attendee-row">
      <div class="form-group" style="flex:1">
        <label class="form-label">Name</label>
        <input class="form-control" type="text" name="attendee_name[]" list="contacts-name-list"
               value="<?= $_ctrl->e($att['name'] ?? '') ?>" placeholder="Name">
      </div>
      <div class="form-group" style="flex:2">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="attendee_email[]" list="contacts-email-list"
               value="<?= $_ctrl->e($att['email'] ?? '') ?>" placeholder="attendee@example.com">
      </div>
      <div class="form-group" style="align-self:flex-end;white-space:nowrap;padding-bottom:10px">
        <label class="form-check">
          <input type="checkbox" name="attendee_rsvp[]" value="1" <?= ($att['rsvp'] ?? '') === 'TRUE' ? 'checked' : '' ?>>
          RSVP
        </label>
      </div>
      <div class="form-group" style="max-width:140px;align-self:flex-end;">
        <button type="button" class="btn btn-ghost remove-attendee">Remove</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="add-attendee" class="btn btn-secondary btn-sm">Add Attendee</button>

  <!-- Recurrence -->
  <div class="form-section-title" style="margin-top:var(--spacing-md)">Recurrence</div>
  <?php
    $rrD       = $e['rrule'] ?? null;
    $rrFreq    = $rrD['freq']  ?? '';
    $rrUntilDate = '';
    if (!empty($rrD['until'])) {
        $u = preg_replace('/[^0-9]/', '', $rrD['until']);
        if (strlen($u) === 8) { $rrUntilDate = substr($u,0,4).'-'.substr($u,4,2).'-'.substr($u,6,2); }
    }
    $rrEndType = !empty($rrD['count']) ? 'count' : (!empty($rrUntilDate) ? 'until' : 'never');
  ?>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="rrule_freq">Repeat</label>
      <select class="form-control" id="rrule_freq" name="rrule_freq" onchange="toggleRrule(this.value)">
        <option value=""        <?= $rrFreq === ''        ? 'selected' : '' ?>>Does not repeat</option>
        <option value="DAILY"   <?= $rrFreq === 'DAILY'   ? 'selected' : '' ?>>Daily</option>
        <option value="WEEKLY"  <?= $rrFreq === 'WEEKLY'  ? 'selected' : '' ?>>Weekly</option>
        <option value="MONTHLY" <?= $rrFreq === 'MONTHLY' ? 'selected' : '' ?>>Monthly</option>
        <option value="YEARLY"  <?= $rrFreq === 'YEARLY'  ? 'selected' : '' ?>>Yearly</option>
      </select>
    </div>
    <div class="form-group" id="rrule_interval_wrap" <?= $rrFreq === '' ? 'style="display:none"' : '' ?>>
      <label class="form-label" for="rrule_interval">Every</label>
      <input class="form-control" type="number" id="rrule_interval" name="rrule_interval"
             min="1" max="999" value="<?= max(1,(int)($rrD['interval'] ?? 1)) ?>">
    </div>
  </div>
  <div id="rrule_end_wrap" <?= $rrFreq === '' ? 'style="display:none"' : '' ?>>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Ends</label>
        <select class="form-control" id="rrule_end_type" name="rrule_end_type" onchange="toggleRruleEnd(this.value)">
          <option value="never" <?= $rrEndType==='never' ? 'selected' : '' ?>>Never</option>
          <option value="count" <?= $rrEndType==='count' ? 'selected' : '' ?>>After N occurrences</option>
          <option value="until" <?= $rrEndType==='until' ? 'selected' : '' ?>>On date</option>
        </select>
      </div>
      <div class="form-group" id="rrule_count_wrap" <?= $rrEndType!=='count' ? 'style="display:none"' : '' ?>>
        <label class="form-label">Occurrences</label>
        <input class="form-control" type="number" id="rrule_count" name="rrule_count"
               min="1" max="999" value="<?= max(1,(int)($rrD['count'] ?? 10)) ?>">
      </div>
      <div class="form-group" id="rrule_until_wrap" <?= $rrEndType!=='until' ? 'style="display:none"' : '' ?>>
        <label class="form-label">End Date</label>
        <input class="form-control" type="date" name="rrule_until"
               value="<?= $_ctrl->e($rrUntilDate) ?>">
      </div>
    </div>
  </div>

  <!-- Reminder -->
  <div class="form-section-title" style="margin-top:var(--spacing-md)">Reminder</div>
  <div class="form-group">
    <label class="form-label" for="alarm_minutes">Notify before event</label>
    <select class="form-control" id="alarm_minutes" name="alarm_minutes">
      <?php
        $savedAlarm  = (int) ($e['alarm_minutes'] ?? 0);
        $alarmOpts   = [0=>'No reminder',5=>'5 min',10=>'10 min',15=>'15 min',30=>'30 min',60=>'1 hour',120=>'2 hours',1440=>'1 day',2880=>'2 days'];
        foreach ($alarmOpts as $v => $lbl): ?>
      <option value="<?= $v ?>" <?= $savedAlarm===$v ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
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

<?php if (!empty($contacts)): ?>
<datalist id="contacts-name-list">
  <?php foreach ($contacts as $ct):
    $ctName = $ct['fn'] ?: trim(($ct['first_name'] ?? '') . ' ' . ($ct['last_name'] ?? ''));
    if ($ctName !== ''): ?>
  <option value="<?= $_ctrl->e($ctName) ?>">
  <?php endif; endforeach; ?>
</datalist>
<datalist id="contacts-email-list">
  <?php foreach ($contacts as $ct):
    $ctName = $ct['fn'] ?: trim(($ct['first_name'] ?? '') . ' ' . ($ct['last_name'] ?? ''));
    foreach ($ct['emails'] ?? [] as $em):
      if (!empty($em['address'])): ?>
  <option value="<?= $_ctrl->e($em['address']) ?>"><?= $_ctrl->e($ctName) ?></option>
  <?php endif; endforeach; endforeach; ?>
</datalist>
<?php endif; ?>

<template id="attendee-row-template">
  <div class="form-row attendee-row">
    <div class="form-group" style="flex:1">
      <label class="form-label">Name</label>
      <input class="form-control" type="text" name="attendee_name[]" list="contacts-name-list" placeholder="Name">
    </div>
    <div class="form-group" style="flex:2">
      <label class="form-label">Email</label>
      <input class="form-control" type="email" name="attendee_email[]" list="contacts-email-list" placeholder="attendee@example.com">
    </div>
    <div class="form-group" style="align-self:flex-end;white-space:nowrap;padding-bottom:10px">
      <label class="form-check">
        <input type="checkbox" name="attendee_rsvp[]" value="1">
        RSVP
      </label>
    </div>
    <div class="form-group" style="max-width:140px;align-self:flex-end;">
      <button type="button" class="btn btn-ghost remove-attendee">Remove</button>
    </div>
  </div>
</template>

<script>
function toggleAllDay(checked) {
  document.getElementById('start_time_group').style.display = checked ? 'none' : '';
  document.getElementById('end_time_group').style.display   = checked ? 'none' : '';
  document.getElementById('timezone_group').style.display   = checked ? 'none' : '';
}
function toggleRrule(freq) {
  const show = freq !== '';
  document.getElementById('rrule_interval_wrap').style.display = show ? '' : 'none';
  document.getElementById('rrule_end_wrap').style.display      = show ? '' : 'none';
}
function toggleRruleEnd(type) {
  document.getElementById('rrule_count_wrap').style.display = type === 'count' ? '' : 'none';
  document.getElementById('rrule_until_wrap').style.display = type === 'until' ? '' : 'none';
}
// Keep end_date in sync with start_date when blank
document.getElementById('start_date').addEventListener('change', function() {
  var endDate = document.getElementById('end_date');
  if (!endDate.value || endDate.value < this.value) { endDate.value = this.value; }
});

(() => {
  const attendeeList = document.getElementById('attendee-list');
  const addAttendeeBtn = document.getElementById('add-attendee');
  const attendeeTpl = document.getElementById('attendee-row-template');

  addAttendeeBtn.addEventListener('click', () => {
    attendeeList.appendChild(attendeeTpl.content.firstElementChild.cloneNode(true));
  });

  attendeeList.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-attendee')) {
      e.target.closest('.attendee-row')?.remove();
    }
  });
})();
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $isEdit ? 'Edit Event' : 'New Event';
require __DIR__ . '/../../templates/layout.php';
