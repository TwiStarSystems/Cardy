<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$appName = \Cardy\Config::get('app.name', 'Cardy');
$davUrl  = \Cardy\Config::get('app.dav_url', 'http://localhost');
$currentUser = $_SESSION['user'] ?? null;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!function_exists('navActive')) {
    function navActive(string $prefix): string {
        global $currentPath;
        return str_starts_with($currentPath, $prefix) ? ' active' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $_ctrl->e($pageTitle ?? $appName) ?> — <?= $_ctrl->e($appName) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📇</text></svg>">
</head>
<body class="app-wrapper">

<nav class="navbar">
  <a href="/dashboard" class="navbar-brand">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
    <?= $_ctrl->e($appName) ?>
  </a>

  <?php if ($currentUser): ?>
  <div class="navbar-nav">
    <a href="/dashboard" class="nav-link<?= navActive('/dashboard') ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      <span>Dashboard</span>
    </a>
    <a href="/contacts" class="nav-link<?= navActive('/contacts') ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
      </svg>
      <span>Contacts</span>
    </a>
    <a href="/calendar" class="nav-link<?= navActive('/calendar') ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <span>Calendar</span>
    </a>
    <?php if (($currentUser['role'] ?? (!empty($currentUser['is_admin']) ? 'admin' : 'user')) === 'admin'): ?>
    <a href="/admin/users" class="nav-link<?= navActive('/admin/users') ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      <span>Admin</span>
    </a>
    <a href="/admin/server" class="nav-link<?= navActive('/admin/server') ?>">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
      <span>Server</span>
    </a>
    <?php endif; ?>
  </div>

  <div class="navbar-user">
    <div class="user-badge">
      <div class="avatar"><?= strtoupper(substr($currentUser['username'], 0, 2)) ?></div>
      <span><?= $_ctrl->e($currentUser['display_name'] ?: $currentUser['username']) ?> (<?= $_ctrl->e($currentUser['role'] ?? (!empty($currentUser['is_admin']) ? 'admin' : 'user')) ?>)</span>
    </div>
    <a href="/logout" class="btn btn-secondary btn-sm">Logout</a>
  </div>
  <?php endif; ?>
</nav>

<main class="main-content">
<?= $content ?? '' ?>
</main>

<footer class="app-footer">
    <p>Made By TwiStarSystems © All Rights Reserved</p>
</footer>

</body>
</html>
