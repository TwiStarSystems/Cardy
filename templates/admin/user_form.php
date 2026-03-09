<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$isEdit  = ($editUser !== null);
$action  = $isEdit ? '/admin/users/' . (int) $editUser['id'] : '/admin/users';
$u       = $editUser ?? [];
$post    = $post ?? [];
?>

<div class="page-header">
  <div>
    <a href="/admin/users" class="text-muted text-sm">← User Management</a>
    <h1 class="page-title" style="margin-top:4px"><?= $isEdit ? 'Edit User' : 'New User' ?></h1>
    <?php if ($isEdit): ?>
    <p class="page-subtitle"><?= $_ctrl->e($u['username']) ?></p>
    <?php endif; ?>
  </div>
  <a href="/admin/users" class="btn btn-secondary">Cancel</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
  <ul style="list-style:disc;padding-left:1.2em;margin:0">
    <?php foreach ($errors as $err): ?>
    <li><?= $_ctrl->e($err) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card">
<form method="POST" action="<?= $action ?>">
  <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">

  <?php if (!$isEdit): ?>
  <div class="form-group">
    <label class="form-label" for="username">Username <span style="color:var(--color-red)">*</span></label>
    <input class="form-control" type="text" id="username" name="username" required autofocus
           pattern="[a-zA-Z0-9_\-]+" minlength="2" maxlength="50"
           value="<?= $_ctrl->e($post['username'] ?? '') ?>"
           placeholder="johndoe">
    <div class="form-hint">Letters, numbers, hyphens and underscores only.</div>
  </div>
  <?php else: ?>
  <div class="form-group">
    <label class="form-label">Username</label>
    <input class="form-control" type="text" value="<?= $_ctrl->e($u['username']) ?>" disabled>
    <div class="form-hint">Username cannot be changed after creation.</div>
  </div>
  <?php endif; ?>

  <div class="form-group">
    <label class="form-label" for="display_name">Display Name</label>
    <input class="form-control" type="text" id="display_name" name="display_name"
           value="<?= $_ctrl->e($isEdit ? $u['display_name'] : ($post['display_name'] ?? '')) ?>"
           placeholder="John Doe">
  </div>

  <div class="form-group">
    <label class="form-label" for="email">Email</label>
    <input class="form-control" type="email" id="email" name="email"
           value="<?= $_ctrl->e($isEdit ? $u['email'] : ($post['email'] ?? '')) ?>"
           placeholder="john@example.com">
  </div>

  <div class="form-group">
    <label class="form-label" for="password">
      <?= $isEdit ? 'New Password' : 'Password' ?>
      <?= !$isEdit ? '<span style="color:var(--color-red)">*</span>' : '' ?>
    </label>
    <input class="form-control" type="password" id="password" name="password"
           <?= !$isEdit ? 'required' : '' ?>
           minlength="8"
           autocomplete="new-password"
           placeholder="<?= $isEdit ? 'Leave blank to keep current password' : 'Minimum 8 characters' ?>">
    <?php if ($isEdit): ?>
    <div class="form-hint">Leave blank to keep the current password.</div>
    <?php endif; ?>
  </div>

  <div class="form-group">
    <label class="form-check">
      <input type="checkbox" name="is_admin" value="1"
             <?= ($isEdit ? $u['is_admin'] : !empty($post['is_admin'])) ? 'checked' : '' ?>>
      <span>Administrator — can manage all users</span>
    </label>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <?= $isEdit ? 'Save Changes' : 'Create User' ?>
    </button>
    <a href="/admin/users" class="btn btn-secondary">Cancel</a>
  </div>
</form>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = $isEdit ? 'Edit User' : 'New User';
require __DIR__ . '/../../templates/layout.php';
