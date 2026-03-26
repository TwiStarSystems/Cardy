<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$qs = static function(array $extra) use ($search, $sort, $category, $groupFilter, $starredOnly): string {
    $p = array_merge(
        ['q' => $search, 'sort' => $sort, 'category' => $category, 'group' => $groupFilter, 'starred' => $starredOnly ? '1' : ''],
        $extra
    );
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null));
};
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Contacts</h1>
    <p class="page-subtitle">
      <?= count($contacts) ?> contact<?= count($contacts) !== 1 ? 's' : '' ?>
      <?= $search ? ' matching "' . $_ctrl->e($search) . '"' : '' ?>
      <?php if ($starredOnly): ?><span class="badge badge-gold" style="margin-left:4px">★ Starred</span><?php endif; ?>
      <?php if ($groupFilter !== ''): ?>
        <?php foreach ($allGroups as $g): ?>
          <?php if ((string)$g['id'] === $groupFilter): ?>
            <span class="badge" style="margin-left:4px;background:<?= $g['color'] ?: 'var(--color-sparkle-purple)' ?>;color:#fff"><?= $_ctrl->e($g['name']) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="flex gap-sm">
    <div class="dropdown" id="contacts-actions-dropdown">
      <button type="button" class="btn btn-secondary" id="contacts-actions-btn">
        Actions
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-left:2px">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>
      <div class="dropdown-menu" id="contacts-actions-menu">
        <a href="/contacts/import" class="dropdown-item">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
          </svg>
          Import Contacts
        </a>
        <div class="dropdown-divider"></div>
        <a href="/contacts/export?format=vcf" class="dropdown-item">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 8l5-5 5 5M12 3v12"/>
          </svg>
          Export as vCard (.vcf)
        </a>
        <a href="/contacts/export?format=icloud_vcf" class="dropdown-item">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 8l5-5 5 5M12 3v12"/>
          </svg>
          Export for iCloud (.vcf)
        </a>
        <a href="/contacts/export?format=csv" class="dropdown-item">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
          </svg>
          Export as CSV (Cardy)
        </a>
        <a href="/contacts/export?format=google_csv" class="dropdown-item">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
          </svg>
          Export for Google Contacts (.csv)
        </a>
        <a href="/contacts/export?format=outlook_csv" class="dropdown-item">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
          </svg>
          Export for Outlook (.csv)
        </a>
        <div class="dropdown-divider"></div>
        <a href="/contacts/groups" class="dropdown-item">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a2 2 0 012-2z"/>
          </svg>
          Manage Groups
        </a>
        <a href="/contacts/duplicates" class="dropdown-item">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          Find Duplicates
        </a>
      </div>
    </div>
    <button type="button" class="btn btn-secondary" id="select-mode-btn" title="Bulk select">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      Select
    </button>
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
  <select class="form-control" name="sort" style="max-width:200px">
    <option value="default"           <?= ($sort ?? 'default') === 'default'           ? 'selected' : '' ?>>Sort: Default</option>
    <option value="first_name"        <?= ($sort ?? '') === 'first_name'               ? 'selected' : '' ?>>Sort: First Name</option>
    <option value="last_name"         <?= ($sort ?? '') === 'last_name'                ? 'selected' : '' ?>>Sort: Last Name</option>
    <option value="birthday"          <?= ($sort ?? '') === 'birthday'                 ? 'selected' : '' ?>>Sort: Birthday</option>
    <option value="organization"      <?= ($sort ?? '') === 'organization'             ? 'selected' : '' ?>>Sort: Organization</option>
    <option value="recently_updated"  <?= ($sort ?? '') === 'recently_updated'         ? 'selected' : '' ?>>Sort: Recently Updated</option>
  </select>
  <select class="form-control" name="category" style="max-width:180px">
    <option value="all"      <?= ($category ?? 'all') === 'all'      ? 'selected' : '' ?>>Category: All</option>
    <option value="people"   <?= ($category ?? '') === 'people'       ? 'selected' : '' ?>>Category: People</option>
    <option value="business" <?= ($category ?? '') === 'business'     ? 'selected' : '' ?>>Category: Business</option>
  </select>
  <?php if (!empty($allGroups)): ?>
  <select class="form-control" name="group" style="max-width:180px">
    <option value="">Group: All</option>
    <?php foreach ($allGroups as $g): ?>
    <option value="<?= (int) $g['id'] ?>" <?= ($groupFilter ?? '') === (string) $g['id'] ? 'selected' : '' ?>>
      Group: <?= $_ctrl->e($g['name']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <label class="flex items-center gap-xs" style="cursor:pointer;font-size:var(--text-sm);white-space:nowrap">
    <input type="checkbox" name="starred" value="1" <?= $starredOnly ? 'checked' : '' ?>>
    ★ Starred
  </label>
  <button type="submit" class="btn btn-secondary">Search</button>
  <?php if ($search || $starredOnly || $groupFilter): ?>
  <a href="/contacts?sort=<?= $_ctrl->e($sort ?? 'default') ?>&amp;category=<?= $_ctrl->e($category ?? 'all') ?>" class="btn btn-ghost">Clear</a>
  <?php endif; ?>
</form>

<!-- Bulk action bar (hidden by default) -->
<div id="bulk-bar" style="display:none;background:var(--color-surface-alt,#f3f0ff);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:var(--spacing-sm) var(--spacing-md);margin-bottom:var(--spacing-md);display:none;align-items:center;gap:var(--spacing-sm);flex-wrap:wrap">
  <span id="bulk-count" style="font-weight:600">0 selected</span>
  <button type="button" id="bulk-select-all" class="btn btn-ghost btn-sm">Select All</button>
  <button type="button" id="bulk-deselect-all" class="btn btn-ghost btn-sm">Deselect All</button>
  <form method="POST" action="/contacts/bulk" id="bulk-form" style="display:contents">
    <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
    <div id="bulk-ids-container"></div>
    <button type="submit" name="action" value="star"   class="btn btn-ghost btn-sm">★ Star</button>
    <button type="submit" name="action" value="unstar" class="btn btn-ghost btn-sm">☆ Unstar</button>
    <?php if (!empty($allGroups)): ?>
    <select name="group_id" id="bulk-group-select" class="form-control" style="max-width:150px">
      <option value="">Add to group…</option>
      <?php foreach ($allGroups as $g): ?>
      <option value="<?= (int) $g['id'] ?>"><?= $_ctrl->e($g['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" name="action" value="add_group" class="btn btn-secondary btn-sm">Add to Group</button>
    <?php endif; ?>
    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm"
            onclick="return confirm('Delete selected contacts? This cannot be undone.')">Delete</button>
  </form>
</div>

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

// Helper to render a contact card
$renderCard = function(array $c, string $type) use ($_ctrl, $csrf): void {
    $initials = strtoupper(
        substr($c['first_name'] ?: $c['fn'], 0, 1) .
        substr($c['last_name'], 0, 1)
    ) ?: '?';
    $badgeClass = $type === 'business' ? 'badge-gold' : 'badge-purple';
    $badgeLabel = $type === 'business' ? 'Business' : 'Person';
    $isStarred  = !empty($c['is_starred']);
?>
  <div class="contact-card-wrapper" data-id="<?= (int) $c['id'] ?>">
    <div class="contact-select-overlay" style="display:none">
      <input type="checkbox" class="contact-checkbox" value="<?= (int) $c['id'] ?>" aria-label="Select <?= $_ctrl->e($c['fn'] ?: 'contact') ?>">
    </div>
    <a href="/contacts/<?= (int) $c['id'] ?>" class="contact-card<?= $isStarred ? ' contact-card-starred' : '' ?>">
      <?php if ($isStarred): ?>
      <span class="star-badge" title="Starred">★</span>
      <?php endif; ?>
      <div class="contact-avatar">
        <?php if (!empty($c['photo'])): ?>
          <img src="data:<?= $_ctrl->e($c['photo_mime'] ?? 'image/jpeg') ?>;base64,<?= $_ctrl->e($c['photo']) ?>" alt="">
        <?php else: ?>
          <?= $_ctrl->e($initials) ?>
        <?php endif; ?>
      </div>
      <div class="contact-info">
        <div class="contact-name"><?= $_ctrl->e($c['fn'] ?: $c['org'] ?: 'Unknown') ?> <span class="text-muted" style="font-size:var(--text-xs)">#<?= (int) $c['id'] ?></span></div>
        <div class="contact-detail"><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></div>
        <?php if (!empty($c['groups'])): ?>
        <div class="contact-detail" style="gap:3px;display:flex;flex-wrap:wrap">
          <?php foreach ($c['groups'] as $g): ?>
          <span class="badge" style="background:<?= $_ctrl->e($g['color'] ?: 'var(--color-sparkle-purple)') ?>;color:#fff;font-size:10px"><?= $_ctrl->e($g['name']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($c['org']): ?>
        <div class="contact-detail"><?= $_ctrl->e($c['org']) ?></div>
        <?php endif; ?>
        <?php if ($c['email']): ?>
        <div class="contact-detail"><?= $_ctrl->e($c['email']) ?></div>
        <?php endif; ?>
      </div>
    </a>
  </div>
<?php
};
?>

<?php if (!empty($peopleContacts)): ?>
<h2 class="contacts-section-title">People Contacts (<?= count($peopleContacts) ?>)</h2>
<div class="contacts-grid" id="people-grid">
  <?php foreach ($peopleContacts as $c): ?>
  <?php $renderCard($c, 'people'); ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($businessContacts)): ?>
<h2 class="contacts-section-title">Business Contacts (<?= count($businessContacts) ?>)</h2>
<div class="contacts-grid" id="business-grid">
  <?php foreach ($businessContacts as $c): ?>
  <?php $renderCard($c, 'business'); ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
(function () {
  // ---------- Actions dropdown ----------
  var btn  = document.getElementById('contacts-actions-btn');
  var menu = document.getElementById('contacts-actions-menu');
  if (btn && menu) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      menu.classList.toggle('show');
    });
    document.addEventListener('click', function () {
      menu.classList.remove('show');
    });
  }

  // ---------- Bulk selection ----------
  var selectModeBtn  = document.getElementById('select-mode-btn');
  var bulkBar        = document.getElementById('bulk-bar');
  var bulkCount      = document.getElementById('bulk-count');
  var bulkIdsContainer = document.getElementById('bulk-ids-container');
  var selectAllBtn   = document.getElementById('bulk-select-all');
  var deselectAllBtn = document.getElementById('bulk-deselect-all');
  var overlays       = document.querySelectorAll('.contact-select-overlay');
  var checkboxes     = document.querySelectorAll('.contact-checkbox');
  var selectMode     = false;

  function getSelectedIds() {
    return Array.from(checkboxes).filter(function(cb){ return cb.checked; }).map(function(cb){ return cb.value; });
  }

  function updateBulkBar() {
    var ids = getSelectedIds();
    bulkCount.textContent = ids.length + ' selected';
    // Rebuild hidden inputs
    bulkIdsContainer.innerHTML = '';
    ids.forEach(function(id) {
      var inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = 'ids[]';
      inp.value = id;
      bulkIdsContainer.appendChild(inp);
    });
  }

  function enableSelectMode() {
    selectMode = true;
    overlays.forEach(function(o){ o.style.display = 'flex'; });
    bulkBar.style.display = 'flex';
    selectModeBtn.classList.add('btn-primary');
    selectModeBtn.classList.remove('btn-secondary');
    updateBulkBar();
  }

  function disableSelectMode() {
    selectMode = false;
    checkboxes.forEach(function(cb){ cb.checked = false; });
    overlays.forEach(function(o){ o.style.display = 'none'; });
    bulkBar.style.display = 'none';
    selectModeBtn.classList.remove('btn-primary');
    selectModeBtn.classList.add('btn-secondary');
  }

  if (selectModeBtn) {
    selectModeBtn.addEventListener('click', function() {
      if (selectMode) { disableSelectMode(); } else { enableSelectMode(); }
    });
  }

  checkboxes.forEach(function(cb) {
    cb.addEventListener('change', function(e) {
      e.stopPropagation();
      updateBulkBar();
    });
  });

  // Clicking a card wrapper in select mode toggles checkbox, not navigation
  document.querySelectorAll('.contact-card-wrapper').forEach(function(wrapper) {
    wrapper.addEventListener('click', function(e) {
      if (!selectMode) return;
      var cb = wrapper.querySelector('.contact-checkbox');
      if (e.target === cb || e.target.closest('a')) return;
      if (cb) { cb.checked = !cb.checked; updateBulkBar(); }
    });
  });

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function() {
      checkboxes.forEach(function(cb){ cb.checked = true; });
      updateBulkBar();
    });
  }
  if (deselectAllBtn) {
    deselectAllBtn.addEventListener('click', function() {
      checkboxes.forEach(function(cb){ cb.checked = false; });
      updateBulkBar();
    });
  }
}());
</script>

<?php
$content   = ob_get_clean();
$pageTitle = 'Contacts';
require __DIR__ . '/../../templates/layout.php';
