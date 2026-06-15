<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$appName = \Cardy\Config::get('app.name', 'Cardy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Set New Password — <?= $_ctrl->e($appName) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📇</text></svg>">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" style="width:36px;height:36px;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>
      <h1>New Password</h1>
      <p><?= $_ctrl->e($appName) ?></p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $_ctrl->e($error) ?></div>
    <?php if (!$token): ?>
    <div style="text-align:center;margin-top:var(--spacing-md)">
      <a href="/forgot-password" class="btn btn-secondary">Request a new link</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($token)): ?>
    <form method="POST" action="/reset-password">
      <input type="hidden" name="_csrf"  value="<?= $_ctrl->e($csrf) ?>">
      <input type="hidden" name="token"  value="<?= $_ctrl->e($token) ?>">

      <div class="form-group">
        <label class="form-label" for="password">New Password</label>
        <input class="form-control" type="password" id="password" name="password"
               required minlength="8" autofocus autocomplete="new-password"
               placeholder="Minimum 8 characters">
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm">Confirm Password</label>
        <input class="form-control" type="password" id="confirm" name="confirm"
               required minlength="8" autocomplete="new-password"
               placeholder="Repeat your new password">
      </div>

      <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:var(--spacing-sm)">
        Set New Password
      </button>
    </form>
    <?php endif; ?>

    <div style="text-align:center;margin-top:var(--spacing-md)">
      <a href="/login" style="font-size:var(--text-sm);color:var(--color-text-muted)">← Back to sign in</a>
    </div>
  </div>
</div>
</body>
</html>
