<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Server Settings</h1>
    <p class="page-subtitle">Admin-only server configuration</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/admin/server">
    <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">

    <div class="form-group">
      <label class="form-label" for="name">Application Name</label>
      <input class="form-control" id="name" name="name" type="text"
             value="<?= $_ctrl->e($app['name'] ?? 'Cardy') ?>" required>
    </div>

    <div class="form-group">
      <label class="form-label" for="timezone">Timezone</label>
      <input class="form-control" id="timezone" name="timezone" type="text"
             value="<?= $_ctrl->e($app['timezone'] ?? 'UTC') ?>" required>
      <div class="form-hint">Example: UTC, America/Toronto, Europe/London</div>
    </div>

    <div class="form-group">
      <label class="form-label" for="webui_url">Web UI URL</label>
      <input class="form-control" id="webui_url" name="webui_url" type="url"
             value="<?= $_ctrl->e($app['webui_url'] ?? 'http://localhost') ?>" required>
    </div>

    <div class="form-group">
      <label class="form-label" for="dav_url">DAV URL</label>
      <input class="form-control" id="dav_url" name="dav_url" type="url"
             value="<?= $_ctrl->e($app['dav_url'] ?? 'http://localhost') ?>" required>
    </div>

    <div class="form-group">
      <label class="form-label" for="trusted_proxies">Trusted Proxies (comma-separated)</label>
      <input class="form-control" id="trusted_proxies" name="trusted_proxies" type="text"
             value="<?= $_ctrl->e(implode(',', $app['trusted_proxies'] ?? ['127.0.0.1','::1'])) ?>">
      <div class="form-hint">IP/CIDR list, e.g. 127.0.0.1,::1 or 10.0.0.0/24</div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Save Server Settings</button>
    </div>
  </form>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Server Settings';
require __DIR__ . '/../../templates/layout.php';
