<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <a href="/contacts" class="text-muted text-sm">← Contacts</a>
    <h1 class="page-title" style="margin-top:4px">Import Contacts</h1>
    <p class="page-subtitle">Import contacts from CSV or vCard — Cardy, Google Contacts, iCloud, and Outlook are all supported</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/contacts/import" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">

    <div class="form-group">
      <label class="form-label" for="import_file">CSV or vCard file</label>
      <input class="form-control" type="file" id="import_file" name="import_file" required accept=".csv,.vcf,.vcard,text/csv,text/vcard,text/x-vcard,text/directory,application/vcard,application/x-vcard">
      <div class="form-hint">The format is detected automatically from the file headers — no manual selection needed.</div>
    </div>

    <div class="form-group">
      <div class="form-label" style="margin-bottom:8px">Supported formats</div>
      <div class="form-hint"><strong>vCard (.vcf)</strong> — exported by Apple Contacts (iCloud), Google Contacts ("Export vCard"), Thunderbird CardBook, and most contact apps. Both single and bulk .vcf files are supported.</div>
      <div class="form-hint" style="margin-top:6px"><strong>Google Contacts CSV</strong> — use <em>Google Contacts → Export → Google CSV</em>. Auto-detected by the <code>Given Name</code> / <code>E-mail 1 - Value</code> columns.</div>
      <div class="form-hint" style="margin-top:6px"><strong>iCloud CSV / vCard</strong> — use <em>iCloud Contacts → Export vCard</em> (preferred) or the iCloud CSV download. vCard files are natively supported.</div>
      <div class="form-hint" style="margin-top:6px"><strong>Outlook CSV</strong> — use <em>Outlook → File → Open &amp; Export → Import/Export → Export to a File → Comma Separated Values</em>. Auto-detected by the <code>E-mail Address</code> / <code>Business Phone</code> columns.</div>
      <div class="form-hint" style="margin-top:6px"><strong>Cardy CSV</strong> — re-import a file previously exported from the Actions → Export as CSV option.</div>
      <div class="form-hint" style="margin-top:8px">
        Download sample CSV: <a href="/assets/examples/contacts-import-template.csv" download>contacts-import-template.csv</a>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Import Contacts</button>
      <a href="/contacts" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Import Contacts';
require __DIR__ . '/../../templates/layout.php';
