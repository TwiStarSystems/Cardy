<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Contacts</h1>
    <p class="page-subtitle"><?= count($contacts) ?> contact<?= count($contacts) !== 1 ? 's' : '' ?><?= $search ? ' matching "' . $_ctrl->e($search) . '"' : '' ?></p>
  </div>
  <div class="flex gap-sm">
    <a href="/contacts/import" class="btn btn-secondary">Import</a>
    <a href="/contacts/new" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      New Contact
    </a>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<form method="GET" action="/contacts" class="search-bar">
  <input
    class="search-input"
    type="search"
    name="q"
    value="<?= $_ctrl->e($search) ?>"
    placeholder="Search by name, email or organization…"
    autofocus
  >
  <button type="submit" class="btn btn-secondary">Search</button>
  <?php if ($search): ?>
  <a href="/contacts" class="btn btn-ghost">Clear</a>
  <?php endif; ?>
</form>

<?php if (empty($contacts)): ?>
<div class="empty-state">
  <div class="empty-state-icon">👤</div>
  <h3>No contacts<?= $search ? ' found' : ' yet' ?></h3>
  <p class="text-muted"><?= $search ? 'Try a different search term.' : 'Add your first contact to get started.' ?></p>
  <?php if (!$search): ?>
  <a href="/contacts/new" class="btn btn-primary mt-sm">Add Contact</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="contacts-grid">
  <?php foreach ($contacts as $c): ?>
  <?php
  $initials = strtoupper(
      substr($c['first_name'] ?: $c['fn'], 0, 1) .
      substr($c['last_name'], 0, 1)
  ) ?: '?';
  ?>
  <a href="/contacts/<?= (int) $c['id'] ?>" class="contact-card">
    <div class="contact-avatar">
      <?php if (!empty($c['photo'])): ?>
        <img src="data:image/jpeg;base64,<?= $_ctrl->e($c['photo']) ?>" alt="">
      <?php else: ?>
        <?= $_ctrl->e($initials) ?>
      <?php endif; ?>
    </div>
    <div class="contact-info">
      <div class="contact-name"><?= $_ctrl->e($c['fn'] ?: $c['org'] ?: 'Unknown') ?> <span class="text-muted" style="font-size:var(--text-xs)">#<?= (int) $c['id'] ?></span></div>
      <?php if ($c['org']): ?>
      <div class="contact-detail"><?= $_ctrl->e($c['org']) ?></div>
      <?php endif; ?>
      <?php if ($c['email']): ?>
      <div class="contact-detail"><?= $_ctrl->e($c['email']) ?></div>
      <?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle = 'Contacts';
require __DIR__ . '/../../templates/layout.php';
