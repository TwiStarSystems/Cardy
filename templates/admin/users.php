<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">User Management</h1>
    <p class="page-subtitle"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></p>
  </div>
  <a href="/admin/users/new" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    New User
  </a>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>Username</th>
        <th>Display Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:var(--spacing-lg)">No users found.</td></tr>
      <?php else: ?>
      <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <div class="flex gap-xs" style="align-items:center">
            <div class="avatar" style="width:30px;height:30px;font-size:var(--text-xs);">
              <?= strtoupper(substr($u['username'], 0, 2)) ?>
            </div>
            <strong><?= $_ctrl->e($u['username']) ?></strong>
          </div>
        </td>
        <td><?= $_ctrl->e($u['display_name']) ?></td>
        <td><?= $_ctrl->e($u['email']) ?></td>
        <td>
          <?php if ($u['is_admin']): ?>
          <span class="badge badge-gold">Admin</span>
          <?php else: ?>
          <span class="badge badge-muted">User</span>
          <?php endif; ?>
        </td>
        <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
        <td>
          <div class="flex gap-xs">
            <a href="/admin/users/<?= (int) $u['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
            <?php if ((int) $u['id'] !== (int) ($_SESSION['user']['id'] ?? 0)): ?>
            <form method="POST" action="/admin/users/<?= (int) $u['id'] ?>/delete">
              <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      onclick="return confirm('Delete user &quot;<?= $_ctrl->e(addslashes($u['username'])) ?>&quot;? All their contacts and calendar events will also be deleted.')">
                Delete
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'User Management';
require __DIR__ . '/../../templates/layout.php';
