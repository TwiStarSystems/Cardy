<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <a href="/contacts" class="text-muted text-sm">← Contacts</a>
    <h1 class="page-title" style="margin-top:4px">Contact Groups</h1>
    <p class="page-subtitle">Organise contacts with labels and groups</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<!-- Create new group -->
<div class="card mb-md">
  <div class="card-header"><span class="card-title">Create New Group</span></div>
  <form method="POST" action="/contacts/groups" style="padding:var(--spacing-md)">
    <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Group Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" maxlength="100" placeholder="e.g. Family, Work, VIP" required>
      </div>
      <div class="form-group">
        <label class="form-label">Colour</label>
        <div class="flex gap-sm align-center">
          <input type="color" name="color" class="form-control" value="#7c3aed" style="max-width:60px;padding:2px;height:38px">
          <span class="text-xs text-muted">Used as label badge colour</span>
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Create Group</button>
  </form>
</div>

<!-- Existing groups -->
<div class="card">
  <div class="card-header"><span class="card-title">Your Groups (<?= count($groups) ?>)</span></div>
  <?php if (empty($groups)): ?>
  <div class="empty-state" style="padding:var(--spacing-lg)">
    <div class="empty-state-icon">🏷️</div>
    <h3>No groups yet</h3>
    <p class="text-muted">Create your first group above to start organising contacts.</p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Group</th>
          <th>Colour</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($groups as $g): ?>
        <tr>
          <td>
            <span class="badge" style="background:<?= $_ctrl->e($g['color'] ?: '#7c3aed') ?>;color:#fff;font-size:var(--text-sm);padding:4px 10px">
              <?= $_ctrl->e($g['name']) ?>
            </span>
          </td>
          <td>
            <span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= $_ctrl->e($g['color'] ?: '#7c3aed') ?>;border:1px solid rgba(0,0,0,.15)"></span>
            <code style="font-size:var(--text-xs)"><?= $_ctrl->e($g['color'] ?: '#7c3aed') ?></code>
          </td>
          <td>
            <div class="flex gap-sm">
              <a href="/contacts?group=<?= (int) $g['id'] ?>" class="btn btn-ghost btn-sm">View Contacts</a>
              <!-- Inline rename form -->
              <button type="button" class="btn btn-ghost btn-sm" onclick="toggleRename(<?= (int) $g['id'] ?>)">Rename</button>
              <form method="POST" action="/contacts/groups/<?= (int) $g['id'] ?>/delete" onsubmit="return confirm('Delete group \'<?= addslashes($_ctrl->e($g['name'])) ?>\'? Contacts will not be deleted.')">
                <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </div>
            <div id="rename-<?= (int) $g['id'] ?>" style="display:none;margin-top:var(--spacing-xs)">
              <form method="POST" action="/contacts/groups/<?= (int) $g['id'] ?>" class="flex gap-sm align-center flex-wrap">
                <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
                <input type="text"  name="name"  class="form-control" value="<?= $_ctrl->e($g['name']) ?>" maxlength="100" required style="max-width:200px">
                <input type="color" name="color" class="form-control" value="<?= $_ctrl->e($g['color'] ?: '#7c3aed') ?>" style="max-width:50px;padding:2px;height:34px">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleRename(id) {
  var el = document.getElementById('rename-' + id);
  if (el) { el.style.display = el.style.display === 'none' ? 'block' : 'none'; }
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = 'Contact Groups';
require __DIR__ . '/../../templates/layout.php';
