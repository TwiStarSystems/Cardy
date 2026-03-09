<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <a href="/contacts" class="text-muted text-sm">← Contacts</a>
    <h1 class="page-title" style="margin-top:4px">Import Contacts</h1>
    <p class="page-subtitle">Import single or bulk contacts from CSV or vCard (.vcf)</p>
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
      <div class="form-hint">Both single and bulk imports are supported. A .vcf file may contain one or many VCARD entries.</div>
    </div>

    <div class="form-group">
      <div class="form-hint">
        CSV supported headers include: <code>first_name</code>, <code>last_name</code>, <code>fn</code>, <code>email</code>/<code>email1..3</code>, <code>phone</code>/<code>phone1..3</code>, <code>org</code>, <code>title</code>, <code>birthday</code>, <code>note</code>, <code>home_*</code>, <code>work_*</code>.
      </div>
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
