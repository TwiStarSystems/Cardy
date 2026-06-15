<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Backup</h1>
    <p class="page-subtitle">Export all contacts and calendar data</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
  <div style="padding:var(--spacing-lg)">
    <h2 style="margin:0 0 var(--spacing-sm)">Download Backup</h2>
    <p class="text-muted" style="margin-bottom:var(--spacing-md)">
      Downloads a <code>.zip</code> file containing all users' contacts (as <code>.vcf</code> files)
      and calendar events (as <code>.ics</code> files), organized by user and address book / calendar.
    </p>
    <form method="POST" action="/admin/backup/download">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <button type="submit" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline;margin-right:4px;vertical-align:text-bottom">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Download Full Backup
      </button>
    </form>
  </div>
</div>

<div class="card" style="margin-top:var(--spacing-lg)">
  <div style="padding:var(--spacing-lg)">
    <h2 style="margin:0 0 var(--spacing-sm)">What's included</h2>
    <ul style="list-style:disc;padding-left:1.4em;margin:0;line-height:2;color:var(--color-text)">
      <li>All contacts from every address book, per user — <code>contacts/{username}/{book}.vcf</code></li>
      <li>All calendar events from every calendar, per user — <code>calendars/{username}/{calendar}.ics</code></li>
      <li>A <code>manifest.json</code> with the export timestamp and server name</li>
    </ul>
    <p class="text-muted" style="margin-top:var(--spacing-md);font-size:var(--text-sm)">
      Passwords, session data, and audit logs are not included. To restore, import the
      <code>.vcf</code> / <code>.ics</code> files via the Contacts / Calendar import pages,
      or feed them directly to a DAV client.
    </p>
  </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'Backup';
require __DIR__ . '/../../templates/layout.php';
