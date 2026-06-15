<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Mail Settings</h1>
    <p class="page-subtitle">SMTP configuration for outgoing email</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:var(--spacing-lg)">
  <form method="POST" action="/admin/mail">
    <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">

    <div class="form-group">
      <label class="form-label" for="host">SMTP Host</label>
      <input class="form-control" type="text" id="host" name="host"
             value="<?= $_ctrl->e($mail['host'] ?? '') ?>"
             placeholder="smtp.example.com">
      <div class="form-hint">Leave blank to disable email features.</div>
    </div>

    <div class="flex gap-sm">
      <div class="form-group" style="flex:1">
        <label class="form-label" for="port">Port</label>
        <input class="form-control" type="number" id="port" name="port"
               min="1" max="65535"
               value="<?= (int) ($mail['port'] ?? 587) ?>">
      </div>
      <div class="form-group" style="flex:2">
        <label class="form-label" for="encryption">Encryption</label>
        <select class="form-control" id="encryption" name="encryption">
          <?php foreach (['tls' => 'STARTTLS (port 587, recommended)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'None (port 25, not recommended)'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= ($mail['encryption'] ?? 'tls') === $val ? 'selected' : '' ?>>
            <?= $label ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="username">SMTP Username</label>
      <input class="form-control" type="text" id="username" name="username"
             value="<?= $_ctrl->e($mail['username'] ?? '') ?>"
             autocomplete="off"
             placeholder="Leave blank if authentication is not required">
    </div>

    <div class="form-group">
      <label class="form-label" for="password">SMTP Password</label>
      <input class="form-control" type="password" id="password" name="password"
             autocomplete="new-password"
             placeholder="<?= !empty($mail['username']) ? 'Leave blank to keep current password' : 'Leave blank if not required' ?>">
      <?php if (!empty($mail['username'])): ?>
      <div class="form-hint">Leave blank to keep the saved password.</div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" for="from_address">From Address <span style="color:var(--color-red)">*</span></label>
      <input class="form-control" type="email" id="from_address" name="from_address"
             value="<?= $_ctrl->e($mail['from_address'] ?? '') ?>"
             placeholder="cardy@yourdomain.com">
    </div>

    <div class="form-group">
      <label class="form-label" for="from_name">From Name</label>
      <input class="form-control" type="text" id="from_name" name="from_name"
             value="<?= $_ctrl->e($mail['from_name'] ?? 'Cardy') ?>">
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Save Mail Settings</button>
    </div>
  </form>
</div>

<div class="card">
  <div style="padding:var(--spacing-md) var(--spacing-lg);border-bottom:1px solid var(--color-border)">
    <h2 style="margin:0;font-size:var(--text-lg)">Send Test Email</h2>
  </div>
  <div style="padding:var(--spacing-lg)">
    <?php if (empty($mail['host']) || empty($mail['from_address'])): ?>
    <p class="text-muted">Save your SMTP settings first before sending a test.</p>
    <?php else: ?>
    <form method="POST" action="/admin/mail/test" class="flex gap-sm" style="align-items:flex-end">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label" for="test_to">Send test to</label>
        <input class="form-control" type="email" id="test_to" name="test_to"
               placeholder="recipient@example.com" required>
      </div>
      <button class="btn btn-secondary" type="submit">Send Test</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:var(--spacing-lg)">
  <div style="padding:var(--spacing-md) var(--spacing-lg);border-bottom:1px solid var(--color-border)">
    <h2 style="margin:0;font-size:var(--text-lg)">What gets emailed</h2>
  </div>
  <div style="padding:var(--spacing-lg)">
    <ul style="list-style:disc;padding-left:1.4em;margin:0;line-height:2">
      <li><strong>Welcome email</strong> — sent to new users when an admin creates their account (if the user has an email address set).</li>
      <li><strong>Password reset</strong> — users can request a reset link from the sign-in page.</li>
      <li><strong>Quota exceeded</strong> — sent to a user when they hit their contact or event limit.</li>
    </ul>
  </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'Mail Settings';
require __DIR__ . '/../../templates/layout.php';
