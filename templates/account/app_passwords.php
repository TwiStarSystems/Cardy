<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">App Passwords</h1>
    <p class="page-subtitle">Create passwords for DAV clients (iOS, Thunderbird, etc.) so they never need your main password.</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<?php if ($newToken !== null): ?>
<div class="alert" style="background:var(--color-success-bg,#d1fae5);border:1px solid var(--color-success,#10b981);border-radius:var(--radius-md);padding:var(--spacing-md)">
  <strong>Your new app password (copy it now — it won't be shown again):</strong>
  <div style="margin-top:var(--spacing-sm);font-family:monospace;font-size:1.1em;letter-spacing:.05em;background:#fff;border:1px solid #ccc;border-radius:var(--radius-sm);padding:var(--spacing-sm) var(--spacing-md);display:inline-block;user-select:all">
    <?= $_ctrl->e($newToken) ?>
  </div>
  <p style="margin-top:var(--spacing-sm);margin-bottom:0;font-size:var(--text-sm)">
    Use this as the password in your DAV client. Your username stays the same.
  </p>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:var(--spacing-lg)">
  <div class="card-header"><strong>Create new app password</strong></div>
  <div class="card-body">
    <form method="POST" action="/account/app-passwords" style="display:flex;gap:var(--spacing-sm);align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
        <label class="form-label" for="app-pw-name">Label <span style="color:var(--color-muted);font-weight:normal">(e.g. "iPhone", "Thunderbird")</span></label>
        <input type="text" id="app-pw-name" name="name" class="form-control" maxlength="100" required placeholder="My phone">
      </div>
      <button type="submit" class="btn btn-primary">Generate password</button>
    </form>
  </div>
</div>

<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>Label</th>
        <th>Created</th>
        <th>Last used</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($passwords)): ?>
      <tr>
        <td colspan="4" class="text-center text-muted" style="padding:var(--spacing-lg)">
          No app passwords yet. Create one above to connect a DAV client.
        </td>
      </tr>
      <?php else: ?>
      <?php foreach ($passwords as $p): ?>
      <tr>
        <td><strong><?= $_ctrl->e($p['name']) ?></strong></td>
        <td><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
        <td><?= $p['last_used_at'] ? date('Y-m-d', strtotime($p['last_used_at'])) : '<span class="text-muted">Never</span>' ?></td>
        <td>
          <form method="POST" action="/account/app-passwords/<?= (int) $p['id'] ?>/delete"
                onsubmit="return confirm('Delete this app password? Any client using it will stop syncing.')">
            <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'App Passwords';
require __DIR__ . '/../../templates/layout.php';
