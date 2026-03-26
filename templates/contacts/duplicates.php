<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <a href="/contacts" class="text-muted text-sm">← Contacts</a>
    <h1 class="page-title" style="margin-top:4px">Find Duplicates</h1>
    <p class="page-subtitle">Contacts that share a name, email address, or phone number</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($duplicates)): ?>
<div class="empty-state">
  <div class="empty-state-icon">✅</div>
  <h3>No duplicates found</h3>
  <p class="text-muted">All contacts appear to be unique.</p>
  <a href="/contacts" class="btn btn-primary mt-sm">Back to Contacts</a>
</div>
<?php else: ?>
<p class="text-muted mb-md"><?= count($duplicates) ?> potential duplicate group<?= count($duplicates) !== 1 ? 's' : '' ?> found. Review each group and merge or ignore as needed.</p>

<?php foreach ($duplicates as $idx => $group): ?>
<div class="card mb-md">
  <div class="card-header">
    <span class="card-title">Group <?= $idx + 1 ?>: <?= $_ctrl->e($group['reason']) ?></span>
    <div class="flex gap-sm">
      <?php if (count($group['contacts']) === 2): ?>
      <a href="/contacts/<?= (int) $group['contacts'][0]['id'] ?>/merge?other=<?= (int) $group['contacts'][1]['id'] ?>" class="btn btn-primary btn-sm">Merge</a>
      <?php endif; ?>
    </div>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Organization</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($group['contacts'] as $dup): ?>
        <tr>
          <td>
            <?= $_ctrl->e($dup['fn'] ?: 'Unknown') ?>
            <span class="text-muted" style="font-size:var(--text-xs)">#<?= (int) $dup['id'] ?></span>
            <?php if (!empty($dup['ignore_duplicate'])): ?>
            <span class="badge badge-muted" style="font-size:10px">ignored</span>
            <?php endif; ?>
          </td>
          <td class="text-sm"><?= $_ctrl->e($dup['email'] ?? '') ?></td>
          <td class="text-sm"><?= $_ctrl->e($dup['phone'] ?? '') ?></td>
          <td class="text-sm"><?= $_ctrl->e($dup['org'] ?? '') ?></td>
          <td>
            <div class="flex gap-sm">
              <a href="/contacts/<?= (int) $dup['id'] ?>" class="btn btn-ghost btn-sm">View</a>
              <a href="/contacts/<?= (int) $dup['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
              <form method="POST" action="/contacts/<?= (int) $dup['id'] ?>/ignore-duplicate">
                <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
                <button type="submit" class="btn btn-ghost btn-sm" title="Mark as not a duplicate">
                  <?= !empty($dup['ignore_duplicate']) ? 'Un-ignore' : 'Ignore' ?>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php
$content   = ob_get_clean();
$pageTitle = 'Find Duplicates';
require __DIR__ . '/../../templates/layout.php';
