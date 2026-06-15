<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$appName = \Cardy\Config::get('app.name', 'Cardy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — <?= $_ctrl->e($appName) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📇</text></svg>">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" style="width:36px;height:36px;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
        </svg>
      </div>
      <h1>Create Account</h1>
      <p><?= $_ctrl->e($appName) ?></p>
    </div>

    <?php if (!empty($invite['note'])): ?>
    <p style="text-align:center;font-size:var(--text-sm);color:var(--color-text-muted);margin-bottom:var(--spacing-md)">
      You were invited by <strong><?= $_ctrl->e($invite['created_by']) ?></strong><?= $invite['note'] ? ' — ' . $_ctrl->e($invite['note']) : '' ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <ul style="list-style:disc;padding-left:1.2em;margin:0">
        <?php foreach ($errors as $err): ?>
        <li><?= $_ctrl->e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $_ctrl->e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($valid)): ?>
    <form method="POST" action="/signup">
      <input type="hidden" name="_csrf"  value="<?= $_ctrl->e($csrf) ?>">
      <input type="hidden" name="token"  value="<?= $_ctrl->e($token) ?>">

      <div class="form-group">
        <label class="form-label" for="username">Username <span style="color:var(--color-red)">*</span></label>
        <input class="form-control" type="text" id="username" name="username" required autofocus
               pattern="[a-zA-Z0-9_\-]+" minlength="2" maxlength="50"
               value="<?= $_ctrl->e($post['username'] ?? '') ?>"
               placeholder="johndoe">
        <div class="form-hint">Letters, numbers, hyphens and underscores only.</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="display_name">Display Name</label>
        <input class="form-control" type="text" id="display_name" name="display_name"
               value="<?= $_ctrl->e($post['display_name'] ?? '') ?>" placeholder="John Doe">
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email"
               value="<?= $_ctrl->e($post['email'] ?? '') ?>" placeholder="john@example.com">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password <span style="color:var(--color-red)">*</span></label>
        <input class="form-control" type="password" id="password" name="password"
               required minlength="8" autocomplete="new-password"
               placeholder="Minimum 8 characters">
      </div>

      <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:var(--spacing-sm)">
        Create Account
      </button>
    </form>
    <?php endif; ?>

    <div style="text-align:center;margin-top:var(--spacing-md)">
      <a href="/login" style="font-size:var(--text-sm);color:var(--color-text-muted)">Already have an account? Sign in</a>
    </div>
  </div>
</div>
</body>
</html>
