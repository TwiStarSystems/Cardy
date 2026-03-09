<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$code    = $code    ?? 404;
$message = $message ?? 'Page not found.';
http_response_code($code);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Error <?= (int) $code ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--gradient-secondary)">
  <div style="text-align:center;padding:var(--spacing-xl)">
    <div style="font-size:5rem;line-height:1;font-weight:800;background:var(--gradient-primary);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"><?= (int) $code ?></div>
    <p style="font-size:var(--text-xl);color:var(--color-white);margin:var(--spacing-sm) 0">
      <?= isset($_ctrl) ? $_ctrl->e($message) : htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
    </p>
    <a href="/dashboard" class="btn btn-primary">Go to Dashboard</a>
  </div>
</body>
</html>
