<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <a href="/calendar" class="text-sm" style="color:var(--color-text-muted)">← Calendar</a>
    <h1 class="page-title" style="margin-top:4px">Import iCal (.ics)</h1>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/calendar/import" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">

    <div class="form-group">
      <label class="form-label" for="ics_file">iCal File (.ics)</label>
      <input class="form-control" type="file" id="ics_file" name="ics_file"
             accept=".ics,text/calendar" required>
      <span class="form-hint">Import events from Google Calendar, Apple Calendar, Outlook, or any CalDAV-compatible application.</span>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Import Events</button>
      <a href="/calendar" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'Import Calendar';
require __DIR__ . '/../../templates/layout.php';
