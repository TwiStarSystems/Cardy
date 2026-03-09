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
  <select class="form-control" name="sort" style="max-width:240px">
    <option value="default" <?= ($sort ?? 'default') === 'default' ? 'selected' : '' ?>>Sort: Default</option>
    <option value="first_name" <?= ($sort ?? '') === 'first_name' ? 'selected' : '' ?>>Sort: First Name</option>
    <option value="last_name" <?= ($sort ?? '') === 'last_name' ? 'selected' : '' ?>>Sort: Last Name</option>
    <option value="birthday" <?= ($sort ?? '') === 'birthday' ? 'selected' : '' ?>>Sort: Birthday</option>
    <option value="organization" <?= ($sort ?? '') === 'organization' ? 'selected' : '' ?>>Sort: Organization</option>
    <option value="recently_updated" <?= ($sort ?? '') === 'recently_updated' ? 'selected' : '' ?>>Sort: Recently Updated</option>
  </select>
  <select class="form-control" name="category" style="max-width:200px">
    <option value="all" <?= ($category ?? 'all') === 'all' ? 'selected' : '' ?>>Category: All</option>
    <option value="people" <?= ($category ?? '') === 'people' ? 'selected' : '' ?>>Category: People</option>
    <option value="business" <?= ($category ?? '') === 'business' ? 'selected' : '' ?>>Category: Business</option>
  </select>
  <button type="submit" class="btn btn-secondary">Search</button>
  <?php if ($search): ?>
  <a href="/contacts?sort=<?= $_ctrl->e($sort ?? 'default') ?>&amp;category=<?= $_ctrl->e($category ?? 'all') ?>" class="btn btn-ghost">Clear</a>
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
<?php
$peopleContacts = [];
$businessContacts = [];
foreach ($contacts as $contactItem) {
  $hasPersonName = trim((string) ($contactItem['first_name'] ?? '')) !== '' || trim((string) ($contactItem['last_name'] ?? '')) !== '';
  $hasOrg = trim((string) ($contactItem['org'] ?? '')) !== '';
  if (!$hasPersonName && $hasOrg) {
    $businessContacts[] = $contactItem;
  } else {
    $peopleContacts[] = $contactItem;
  }
}
?>
<?php if (!empty($peopleContacts)): ?>
<h2 class="contacts-section-title">People Contacts (<?= count($peopleContacts) ?>)</h2>
<div class="contacts-grid">
  <?php foreach ($peopleContacts as $c): ?>
  <?php
  $initials = strtoupper(
      substr($c['first_name'] ?: $c['fn'], 0, 1) .
      substr($c['last_name'], 0, 1)
  ) ?: '?';
  ?>
  <a href="/contacts/<?= (int) $c['id'] ?>" class="contact-card">
    <div class="contact-avatar">
      <?php if (!empty($c['photo'])): ?>
        <img src="data:<?= $_ctrl->e($c['photo_mime'] ?? 'image/jpeg') ?>;base64,<?= $_ctrl->e($c['photo']) ?>" alt="">
      <?php else: ?>
        <?= $_ctrl->e($initials) ?>
      <?php endif; ?>
    </div>
    <div class="contact-info">
      <div class="contact-name"><?= $_ctrl->e($c['fn'] ?: $c['org'] ?: 'Unknown') ?> <span class="text-muted" style="font-size:var(--text-xs)">#<?= (int) $c['id'] ?></span></div>
      <div class="contact-detail"><span class="badge badge-purple">Person</span></div>
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

<?php if (!empty($businessContacts)): ?>
<h2 class="contacts-section-title">Business Contacts (<?= count($businessContacts) ?>)</h2>
<div class="contacts-grid">
  <?php foreach ($businessContacts as $c): ?>
  <?php
  $initials = strtoupper(
      substr($c['first_name'] ?: $c['fn'], 0, 1) .
      substr($c['last_name'], 0, 1)
  ) ?: '?';
  ?>
  <a href="/contacts/<?= (int) $c['id'] ?>" class="contact-card">
    <div class="contact-avatar">
      <?php if (!empty($c['photo'])): ?>
        <img src="data:<?= $_ctrl->e($c['photo_mime'] ?? 'image/jpeg') ?>;base64,<?= $_ctrl->e($c['photo']) ?>" alt="">
      <?php else: ?>
        <?= $_ctrl->e($initials) ?>
      <?php endif; ?>
    </div>
    <div class="contact-info">
      <div class="contact-name"><?= $_ctrl->e($c['fn'] ?: $c['org'] ?: 'Unknown') ?> <span class="text-muted" style="font-size:var(--text-xs)">#<?= (int) $c['id'] ?></span></div>
      <div class="contact-detail"><span class="badge badge-gold">Business</span></div>
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
<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle = 'Contacts';
require __DIR__ . '/../../templates/layout.php';
