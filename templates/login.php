<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$appName = \Cardy\Config::get('app.name', 'Cardy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= $_ctrl->e($appName) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📇</text></svg>">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" style="width:36px;height:36px;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
      </div>
      <h1><?= $_ctrl->e($appName) ?></h1>
      <p>CardDAV &amp; CalDAV Server</p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $_ctrl->e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input
          class="form-control"
          type="text"
          id="username"
          name="username"
          value="<?= $_ctrl->e($username ?? '') ?>"
          required
          autofocus
          autocomplete="username"
          placeholder="Enter your username"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input
          class="form-control"
          type="password"
          id="password"
          name="password"
          required
          autocomplete="current-password"
          placeholder="Enter your password"
        >
      </div>

      <button type="submit" class="btn btn-primary w-full btn-lg" style="margin-top:var(--spacing-sm)">
        Sign In
      </button>
    </form>
  </div>
</div>
</body>
</html>
