<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$appName = \Cardy\Config::get('app.name', 'Cardy');
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Account Security</h1>
    <p class="page-subtitle"><?= $_ctrl->e($user['display_name'] ?: $user['username']) ?></p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<!-- ── Two-Factor Authentication ── -->
<div class="card" style="margin-bottom:var(--spacing-lg)">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
    <strong>Two-Factor Authentication (TOTP)</strong>
    <?php if ($totpEnabled): ?>
    <span class="badge" style="background:var(--color-success,#10b981);color:#fff">Enabled</span>
    <?php else: ?>
    <span class="badge badge-muted">Disabled</span>
    <?php endif; ?>
  </div>
  <div class="card-body">

    <?php if ($totpEnabled && $totpSecret === null): ?>
      <p style="margin:0 0 var(--spacing-md)">
        Your account is protected by a time-based one-time password. You will be asked for a code from your authenticator app each time you log in.
      </p>
      <form method="POST" action="/account/totp/disable"
            onsubmit="return confirm('This will remove 2FA protection from your account. Continue?')">
        <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
        <button type="submit" class="btn btn-danger">Disable 2FA</button>
      </form>

    <?php elseif ($totpSecret !== null): ?>
      <?php
        $otpauthUrl = \Cardy\Totp::getOtpauthUrl($totpSecret, $user['username'], $appName);
      ?>
      <p style="margin:0 0 var(--spacing-md)">
        Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.), then enter the 6-digit code below to confirm.
      </p>
      <div style="display:flex;gap:var(--spacing-lg);flex-wrap:wrap;align-items:flex-start">
        <div>
          <div id="qrcode" style="background:#fff;padding:12px;display:inline-block;border:1px solid var(--color-border);border-radius:var(--radius-md)"></div>
          <p style="margin:var(--spacing-xs) 0 0;font-size:var(--text-xs);color:var(--color-muted);max-width:200px;word-break:break-all">
            <?= $_ctrl->e($totpSecret) ?>
          </p>
        </div>
        <form method="POST" action="/account/totp/confirm" style="flex:1;min-width:220px">
          <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
          <div class="form-group">
            <label class="form-label" for="totp-code">Confirmation code</label>
            <input type="text" id="totp-code" name="code" class="form-control"
                   inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7"
                   required autofocus autocomplete="one-time-code" placeholder="000000"
                   style="font-size:1.3em;letter-spacing:.15em;max-width:160px">
          </div>
          <div class="flex gap-xs">
            <button type="submit" class="btn btn-primary">Confirm &amp; Enable</button>
            <a href="/account/totp/cancel" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
              integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSrmMzvUKfX6LNx5bFMkWokVISsTq7es2dkA=="
              crossorigin="anonymous" referrerpolicy="no-referrer"
              nonce="<?= $_ctrl->nonce() ?>"></script>
      <script nonce="<?= $_ctrl->nonce() ?>">
      new QRCode(document.getElementById('qrcode'), {
        text: <?= json_encode($otpauthUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        width: 200, height: 200,
        colorDark: '#1a1a2e', colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
      </script>

    <?php else: ?>
      <p style="margin:0 0 var(--spacing-md)">
        Add an extra layer of security. After enabling, you will need your authenticator app in addition to your password when logging in.
      </p>
      <form method="POST" action="/account/totp/setup">
        <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
        <button type="submit" class="btn btn-primary">Set up 2FA</button>
      </form>
    <?php endif; ?>

  </div>
</div>

<!-- ── App-specific Passwords ── -->
<div class="card" style="margin-bottom:var(--spacing-lg)">
  <div class="card-header"><strong>App Passwords (for DAV clients)</strong></div>
  <div class="card-body">
    <p style="margin:0 0 var(--spacing-md);font-size:var(--text-sm)">
      Generate a password for each DAV client (iOS, Thunderbird, etc.). These passwords bypass 2FA — your main password is never shared with clients.
    </p>

    <?php if ($newToken !== null): ?>
    <div class="alert" style="background:var(--color-success-bg,#d1fae5);border:1px solid var(--color-success,#10b981);border-radius:var(--radius-md);padding:var(--spacing-md);margin-bottom:var(--spacing-md)">
      <strong>New app password (copy it now — it won't be shown again):</strong>
      <div style="margin-top:var(--spacing-sm);font-family:monospace;font-size:1.05em;letter-spacing:.05em;background:#fff;border:1px solid #ccc;border-radius:var(--radius-sm);padding:var(--spacing-sm) var(--spacing-md);display:inline-block;user-select:all">
        <?= $_ctrl->e($newToken) ?>
      </div>
      <p style="margin:var(--spacing-sm) 0 0;font-size:var(--text-sm)">
        Use this as the password in your DAV client. Your username stays the same.
      </p>
    </div>
    <?php endif; ?>

    <form method="POST" action="/account/app-passwords" style="display:flex;gap:var(--spacing-sm);align-items:flex-end;flex-wrap:wrap;margin-bottom:var(--spacing-md)">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
        <label class="form-label" for="app-pw-name">Label</label>
        <input type="text" id="app-pw-name" name="name" class="form-control" maxlength="100" required placeholder="e.g. iPhone, Thunderbird">
      </div>
      <button type="submit" class="btn btn-primary">Generate password</button>
    </form>

    <?php if (!empty($passwords)): ?>
    <div class="table-wrapper" style="margin-bottom:0">
      <table>
        <thead>
          <tr><th>Label</th><th>Created</th><th>Last used</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($passwords as $p): ?>
          <tr>
            <td><strong><?= $_ctrl->e($p['name']) ?></strong></td>
            <td><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
            <td><?= $p['last_used_at'] ? date('Y-m-d', strtotime($p['last_used_at'])) : '<span class="text-muted">Never</span>' ?></td>
            <td>
              <form method="POST" action="/account/app-passwords/<?= (int) $p['id'] ?>/delete"
                    onsubmit="return confirm('Revoke this app password? Any client using it will stop syncing.')">
                <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted" style="margin:0;font-size:var(--text-sm)">No app passwords yet.</p>
    <?php endif; ?>
  </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = 'Account Security';
require __DIR__ . '/../../templates/layout.php';
