<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$c = $contact;
$initials = strtoupper(
    substr($c['first_name'] ?: $c['fn'], 0, 1) .
    substr($c['last_name'], 0, 1)
) ?: '?';
?>

<div class="page-header">
  <div>
    <a href="/contacts" class="text-muted text-sm">← Contacts</a>
    <h1 class="page-title" style="margin-top:4px"><?= $_ctrl->e($c['fn'] ?: 'Unknown') ?></h1>
    <?php if ($c['org']): ?>
    <p class="page-subtitle"><?= $_ctrl->e($c['org']) ?></p>
    <?php endif; ?>
  </div>
  <div class="flex gap-sm">
    <a href="/contacts/<?= (int) $c['id'] ?>/edit" class="btn btn-primary">Edit Contact</a>
    <form method="POST" action="/contacts/<?= (int) $c['id'] ?>/delete">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <button type="submit" class="btn btn-danger"
              onclick="return confirm('Delete this contact? This cannot be undone.')">
        Delete
      </button>
    </form>
  </div>
</div>

<div class="card">
  <!-- Header -->
  <div class="contact-detail-header">
    <div class="contact-detail-avatar">
      <?php if (!empty($c['photo'])): ?>
        <img src="data:image/jpeg;base64,<?= $_ctrl->e($c['photo']) ?>" alt="">
      <?php else: ?>
        <?= $_ctrl->e($initials) ?>
      <?php endif; ?>
    </div>
    <div class="contact-detail-meta">
      <h2><?= $_ctrl->e($c['fn'] ?: 'Unknown') ?></h2>
      <?php if ($c['title']): ?>
      <p class="org"><?= $_ctrl->e($c['title']) ?><?= $c['org'] ? ' @ ' . $_ctrl->e($c['org']) : '' ?></p>
      <?php elseif ($c['org']): ?>
      <p class="org"><?= $_ctrl->e($c['org']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <hr class="divider">

  <!-- Details -->
  <div class="detail-grid">

    <?php if (!empty($c['emails'])): ?>
    <div class="detail-group">
      <div class="detail-label">Email</div>
      <?php foreach ($c['emails'] as $email): ?>
      <div class="detail-value">
        <a href="mailto:<?= $_ctrl->e($email['address']) ?>"><?= $_ctrl->e($email['address']) ?></a>
        <?php if ($email['type']): ?>
        <span class="badge badge-muted"><?= $_ctrl->e($email['type']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($c['phones'])): ?>
    <div class="detail-group">
      <div class="detail-label">Phone</div>
      <?php foreach ($c['phones'] as $phone): ?>
      <div class="detail-value">
        <a href="tel:<?= $_ctrl->e($phone['number']) ?>"><?= $_ctrl->e($phone['number']) ?></a>
        <?php if ($phone['type']): ?>
        <span class="badge badge-muted"><?= $_ctrl->e($phone['type']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($c['birthday']): ?>
    <div class="detail-group">
      <div class="detail-label">Birthday</div>
      <div class="detail-value"><?= $_ctrl->e($c['birthday']) ?></div>
    </div>
    <?php endif; ?>

    <?php foreach ($c['addresses'] as $addr): ?>
    <div class="detail-group">
      <div class="detail-label"><?= ucfirst($_ctrl->e($addr['type'] ?? 'Address')) ?> Address</div>
      <div class="detail-value">
        <?php
        $parts = array_filter([
            $addr['street']   ?? '',
            $addr['city']     ?? '',
            $addr['region']   ?? '',
            $addr['postcode'] ?? '',
            $addr['country']  ?? '',
        ]);
        echo nl2br($_ctrl->e(implode(', ', $parts)));
        ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if ($c['note']): ?>
    <div class="detail-group" style="grid-column: 1 / -1">
      <div class="detail-label">Notes</div>
      <div class="detail-value"><?= nl2br($_ctrl->e($c['note'])) ?></div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = $c['fn'] ?: 'Contact';
require __DIR__ . '/../../templates/layout.php';
