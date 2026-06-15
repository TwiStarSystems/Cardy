<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$appName = \Cardy\Config::get('app.name', 'Cardy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — <?= $_ctrl->e($appName) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📇</text></svg>">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" style="width:36px;height:36px;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
        </svg>
      </div>
      <h1>Reset Password</h1>
      <p><?= $_ctrl->e($appName) ?></p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $_ctrl->e($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $_ctrl->e($success) ?></div>
    <?php else: ?>

    <?php if (empty($mailConfigured)): ?>
    <div class="alert alert-error">Password reset by email is not configured on this server. Contact your administrator.</div>
    <?php else: ?>
    <p style="font-size:var(--text-sm);color:var(--color-text-muted);margin-bottom:var(--spacing-md);text-align:center">
      Enter the email address on your account and we'll send a reset link.
    </p>
    <form method="POST" action="/forgot-password">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input class="form-control" type="email" id="email" name="email" required autofocus
               placeholder="you@example.com">
      </div>
      <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:var(--spacing-sm)">
        Send Reset Link
      </button>
    </form>
    <?php endif; ?>

    <?php endif; ?>

    <div style="text-align:center;margin-top:var(--spacing-md)">
      <a href="/login" style="font-size:var(--text-sm);color:var(--color-text-muted)">← Back to sign in</a>
    </div>
  </div>
</div>
</body>
</html>
