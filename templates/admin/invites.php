<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Invite Links</h1>
    <p class="page-subtitle">Generate sign-up links for new users</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:var(--spacing-lg)">
  <div style="padding:var(--spacing-md) var(--spacing-lg);border-bottom:1px solid var(--color-border)">
    <h2 style="margin:0;font-size:var(--text-lg)">Create Invite</h2>
  </div>
  <form method="POST" action="/admin/invites" style="padding:var(--spacing-lg)">
    <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
    <div class="flex gap-md" style="align-items:flex-end;flex-wrap:wrap">
      <div class="form-group" style="flex:3;min-width:200px;margin-bottom:0">
        <label class="form-label" for="note">Label / Note (optional)</label>
        <input class="form-control" type="text" id="note" name="note"
               placeholder="e.g. For Alice's account" maxlength="255">
      </div>
      <div class="form-group" style="flex:1;min-width:100px;margin-bottom:0">
        <label class="form-label" for="max_uses">Max uses</label>
        <input class="form-control" type="number" id="max_uses" name="max_uses" min="1" value="1">
      </div>
      <div class="form-group" style="flex:1;min-width:120px;margin-bottom:0">
        <label class="form-label" for="expires_in">Expires in (days)</label>
        <input class="form-control" type="number" id="expires_in" name="expires_in"
               min="1" placeholder="Never">
      </div>
      <div style="padding-bottom:2px">
        <button type="submit" class="btn btn-primary">Create Link</button>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div style="padding:var(--spacing-md) var(--spacing-lg);border-bottom:1px solid var(--color-border)">
    <h2 style="margin:0;font-size:var(--text-lg)">Active Invites</h2>
  </div>
  <?php if (empty($invites)): ?>
  <p class="text-muted" style="padding:var(--spacing-lg)">No invite links yet.</p>
  <?php else: ?>
  <div class="table-wrapper" style="margin:0">
    <table>
      <thead>
        <tr>
          <th>Link</th>
          <th>Note</th>
          <th>Uses</th>
          <th>Expires</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invites as $inv):
          $url      = $signupBase . $_ctrl->e($inv['token']);
          $used     = (int) $inv['uses_count'];
          $max      = (int) $inv['max_uses'];
          $expired  = $inv['expires_at'] && strtotime($inv['expires_at']) < time();
          $exhausted = $used >= $max;
        ?>
        <tr>
          <td style="max-width:320px">
            <div class="flex gap-xs" style="align-items:center">
              <input type="text" class="form-control" style="font-size:var(--text-xs);padding:4px 6px"
                     value="<?= $url ?>" readonly onclick="this.select()">
              <button type="button" class="btn btn-ghost btn-sm"
                      onclick="navigator.clipboard.writeText(<?= json_encode($url) ?>);this.textContent='✓'">Copy</button>
            </div>
          </td>
          <td class="text-sm"><?= $_ctrl->e($inv['note']) ?></td>
          <td>
            <?= $used ?> / <?= $max ?>
            <?php if ($exhausted): ?>
            <span class="badge badge-danger" style="margin-left:4px">Used up</span>
            <?php endif; ?>
          </td>
          <td class="text-sm text-muted">
            <?php if ($inv['expires_at']): ?>
              <?= $expired ? '<span class="badge badge-danger">Expired</span>' : $_ctrl->e(date('Y-m-d', strtotime($inv['expires_at']))) ?>
            <?php else: ?>
              Never
            <?php endif; ?>
          </td>
          <td class="text-sm text-muted"><?= date('Y-m-d', strtotime($inv['created_at'])) ?></td>
          <td>
            <form method="POST" action="/admin/invites/<?= (int) $inv['id'] ?>/delete">
              <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                      onclick="return confirm('Revoke this invite link?')">Revoke</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'Invite Links';
require __DIR__ . '/../../templates/layout.php';
