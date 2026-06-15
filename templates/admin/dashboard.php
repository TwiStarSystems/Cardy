<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();

function fmtBytes(int $bytes): string {
    if ($bytes >= 1024 * 1024) return round($bytes / (1024 * 1024), 1) . ' MB';
    if ($bytes >= 1024)        return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Admin Overview</h1>
    <p class="page-subtitle">System-wide usage at a glance</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<!-- Summary cards -->
<div class="flex gap-md" style="flex-wrap:wrap;margin-bottom:var(--spacing-xl)">
  <div class="card" style="flex:1;min-width:160px;text-align:center;padding:var(--spacing-lg)">
    <div style="font-size:2rem;font-weight:700;color:var(--color-twistar-purple)"><?= $totalUsers ?></div>
    <div class="text-muted" style="font-size:var(--text-sm);margin-top:4px">Users</div>
  </div>
  <div class="card" style="flex:1;min-width:160px;text-align:center;padding:var(--spacing-lg)">
    <div style="font-size:2rem;font-weight:700;color:var(--color-twistar-purple)"><?= number_format($totalContacts) ?></div>
    <div class="text-muted" style="font-size:var(--text-sm);margin-top:4px">Contacts</div>
  </div>
  <div class="card" style="flex:1;min-width:160px;text-align:center;padding:var(--spacing-lg)">
    <div style="font-size:2rem;font-weight:700;color:var(--color-twistar-purple)"><?= number_format($totalEvents) ?></div>
    <div class="text-muted" style="font-size:var(--text-sm);margin-top:4px">Calendar Events</div>
  </div>
  <div class="card" style="flex:1;min-width:160px;text-align:center;padding:var(--spacing-lg)">
    <div style="font-size:2rem;font-weight:700;color:var(--color-twistar-purple)"><?= number_format($totalTasks) ?></div>
    <div class="text-muted" style="font-size:var(--text-sm);margin-top:4px">Tasks</div>
  </div>
  <div class="card" style="flex:1;min-width:160px;text-align:center;padding:var(--spacing-lg)">
    <div style="font-size:2rem;font-weight:700;color:var(--color-twistar-purple)"><?= fmtBytes($contactBytes + $eventBytes) ?></div>
    <div class="text-muted" style="font-size:var(--text-sm);margin-top:4px">Data Stored</div>
  </div>
</div>

<!-- Per-user breakdown -->
<div class="card">
  <div style="padding:var(--spacing-md) var(--spacing-lg);border-bottom:1px solid var(--color-border)">
    <h2 style="margin:0;font-size:var(--text-lg)">Per-User Breakdown</h2>
  </div>
  <div class="table-wrapper" style="margin:0">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>Contacts</th>
          <th>Events</th>
          <th>Last Login</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:var(--spacing-lg)">No users.</td></tr>
        <?php else: ?>
        <?php foreach ($users as $u): ?>
        <?php
            $cq = (int)($u['contact_quota'] ?? 0);
            $cc = (int)($u['contact_count'] ?? 0);
            $eq = (int)($u['event_quota']   ?? 0);
            $ec = (int)($u['event_count']   ?? 0);
            $cPct = ($cq > 0) ? min(100, (int) round($cc / $cq * 100)) : null;
            $ePct = ($eq > 0) ? min(100, (int) round($ec / $eq * 100)) : null;
        ?>
        <tr>
          <td>
            <div class="flex gap-xs" style="align-items:center">
              <div class="avatar" style="width:30px;height:30px;font-size:var(--text-xs)">
                <?= strtoupper(substr($u['username'], 0, 2)) ?>
              </div>
              <div>
                <strong><?= $_ctrl->e($u['username']) ?></strong>
                <?php if ($u['display_name'] && $u['display_name'] !== $u['username']): ?>
                <div class="text-muted" style="font-size:var(--text-xs)"><?= $_ctrl->e($u['display_name']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <?php if (($u['role'] ?? '') === 'admin' || !empty($u['is_admin'])): ?>
            <span class="badge badge-gold">Admin</span>
            <?php else: ?>
            <span class="badge badge-muted">User</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $cc ?><?= $cq > 0 ? ' / ' . $cq : '' ?>
            <?php if ($cPct !== null): ?>
            <div style="margin-top:4px;height:4px;background:var(--color-border);border-radius:2px;width:80px">
              <div style="height:4px;background:<?= $cPct >= 90 ? 'var(--color-red)' : 'var(--color-twistar-purple)' ?>;border-radius:2px;width:<?= $cPct ?>%"></div>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <?= $ec ?><?= $eq > 0 ? ' / ' . $eq : '' ?>
            <?php if ($ePct !== null): ?>
            <div style="margin-top:4px;height:4px;background:var(--color-border);border-radius:2px;width:80px">
              <div style="height:4px;background:<?= $ePct >= 90 ? 'var(--color-red)' : 'var(--color-twistar-purple)' ?>;border-radius:2px;width:<?= $ePct ?>%"></div>
            </div>
            <?php endif; ?>
          </td>
          <td class="text-muted" style="font-size:var(--text-sm)">
            <?= $u['last_login'] ? date('Y-m-d H:i', strtotime($u['last_login'])) : '—' ?>
          </td>
          <td>
            <a href="/admin/users/<?= (int)$u['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'Admin Overview';
require __DIR__ . '/../../templates/layout.php';
