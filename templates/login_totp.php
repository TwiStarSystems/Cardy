<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$appName = \Cardy\Config::get('app.name', 'Cardy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Two-Factor Authentication — <?= $_ctrl->e($appName) ?></title>
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
      <h1>Two-Factor Auth</h1>
      <p>Enter the code from your authenticator app</p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $_ctrl->e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login/totp" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">

      <div class="form-group">
        <label class="form-label" for="code">Authentication Code</label>
        <input
          class="form-control"
          type="text"
          id="code"
          name="code"
          inputmode="numeric"
          pattern="[0-9 ]{6,7}"
          maxlength="7"
          required
          autofocus
          autocomplete="one-time-code"
          placeholder="000000"
          style="font-size:1.5em;letter-spacing:.2em;text-align:center"
        >
      </div>

      <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:var(--spacing-sm)">
        Verify
      </button>
    </form>

    <p style="text-align:center;margin-top:var(--spacing-md);font-size:var(--text-sm)">
      <a href="/login" style="color:var(--color-muted)">← Back to login</a>
    </p>
  </div>
</div>
</body>
</html>
