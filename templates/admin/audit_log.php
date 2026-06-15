<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();

$actionLabels = [
    'login.success'       => ['Login',          'badge-success'],
    'login.failure'       => ['Login failed',   'badge-error'],
    'login.blocked'       => ['Rate limited',   'badge-error'],
    'login.totp_required' => ['TOTP required',  'badge-muted'],
    'login.totp_failure'  => ['TOTP failed',    'badge-error'],
    'login.totp_blocked'  => ['TOTP blocked',   'badge-error'],
    'logout'              => ['Logout',          'badge-muted'],
    'session.timeout'     => ['Session timeout','badge-muted'],
    'totp.enabled'        => ['2FA enabled',    'badge-success'],
    'totp.disabled'       => ['2FA disabled',   'badge-warning'],
    'apppassword.create'  => ['App pw created', 'badge-muted'],
    'apppassword.delete'  => ['App pw revoked', 'badge-muted'],
    'admin.user.create'   => ['User created',   'badge-gold'],
    'admin.user.update'   => ['User updated',   'badge-gold'],
    'admin.user.delete'   => ['User deleted',   'badge-error'],
    'admin.server.update' => ['Server updated', 'badge-gold'],
    'calendar.share'      => ['Cal shared',     'badge-muted'],
    'calendar.unshare'    => ['Cal unshared',   'badge-muted'],
    'addressbook.share'   => ['Book shared',    'badge-muted'],
    'addressbook.unshare' => ['Book unshared',  'badge-muted'],
];

$filterOptions = [
    ''       => 'All events',
    'login'  => 'Logins',
    'admin'  => 'Admin actions',
    'totp'   => '2FA',
    'apppassword' => 'App passwords',
    'session' => 'Sessions',
];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Audit Log</h1>
    <p class="page-subtitle">Last <?= count($entries) ?> events</p>
  </div>
  <form method="GET" action="/admin/audit-log" style="display:flex;gap:var(--spacing-sm);align-items:center">
    <select name="action" class="form-control" style="width:auto" onchange="this.form.submit()">
      <?php foreach ($filterOptions as $val => $label): ?>
      <option value="<?= $_ctrl->e($val) ?>" <?= $filter === $val ? 'selected' : '' ?>><?= $_ctrl->e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th style="white-space:nowrap">Time</th>
        <th>User</th>
        <th>Event</th>
        <th>Detail</th>
        <th>IP</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($entries)): ?>
      <tr>
        <td colspan="5" class="text-center text-muted" style="padding:var(--spacing-lg)">No events recorded yet.</td>
      </tr>
      <?php else: ?>
      <?php foreach ($entries as $e): ?>
      <?php
        [$label, $badgeClass] = $actionLabels[$e['action']] ?? [$e['action'], 'badge-muted'];
      ?>
      <tr>
        <td style="white-space:nowrap;font-size:var(--text-sm);color:var(--color-muted)">
          <?= date('Y-m-d H:i:s', strtotime($e['created_at'])) ?>
        </td>
        <td>
          <?php if ($e['username'] !== ''): ?>
          <strong><?= $_ctrl->e($e['username']) ?></strong>
          <?php else: ?>
          <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= $badgeClass ?>"><?= $_ctrl->e($label) ?></span></td>
        <td style="font-size:var(--text-sm);color:var(--color-muted)">
          <?= $_ctrl->e($e['detail'] ?? '') ?>
        </td>
        <td style="font-size:var(--text-sm);color:var(--color-muted);font-family:monospace">
          <?= $_ctrl->e($e['ip']) ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'Audit Log';
require __DIR__ . '/../../templates/layout.php';
