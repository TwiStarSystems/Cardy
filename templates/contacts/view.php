<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$c = $contact;
$initials = strtoupper(
    substr($c['first_name'] ?: $c['fn'], 0, 1) .
    substr($c['last_name'], 0, 1)
) ?: '?';
$isStarred = !empty($c['is_starred']);

// Build a stripped vCard string for QR (no photo — keeps QR compact)
$qrData = $c;
$qrData['photo'] = '';
$qrData['photo_upload'] = null;
$vcardText = \Cardy\Models\Contact::buildVCard($qrData);
?>

<div class="page-header">
  <div>
    <a href="/contacts" class="text-muted text-sm">← Contacts</a>
    <h1 class="page-title" style="margin-top:4px">
      <?= $isStarred ? '<span style="color:#f59e0b;margin-right:4px" title="Starred">★</span>' : '' ?>
      <?= $_ctrl->e($c['fn'] ?: 'Unknown') ?>
      <span class="text-muted" style="font-size:var(--text-sm)">#<?= (int) $c['id'] ?></span>
    </h1>
    <?php if ($c['org']): ?>
    <p class="page-subtitle"><?= $_ctrl->e($c['org']) ?></p>
    <?php endif; ?>
  </div>
  <div class="flex gap-sm flex-wrap">
    <form method="POST" action="/contacts/<?= (int) $c['id'] ?>/star">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <button type="submit" class="btn <?= $isStarred ? 'btn-secondary' : 'btn-ghost' ?>" title="<?= $isStarred ? 'Unstar' : 'Star' ?> this contact">
        <?= $isStarred ? '★ Starred' : '☆ Star' ?>
      </button>
    </form>
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
        <img src="data:<?= $_ctrl->e($c['photo_mime'] ?? 'image/jpeg') ?>;base64,<?= $_ctrl->e($c['photo']) ?>" alt="">
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
      <?php if (!empty($c['groups'])): ?>
      <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px">
        <?php foreach ($c['groups'] as $g): ?>
        <a href="/contacts?group=<?= (int) $g['id'] ?>" class="badge" style="background:<?= $_ctrl->e($g['color'] ?: 'var(--color-sparkle-purple)') ?>;color:#fff;text-decoration:none"><?= $_ctrl->e($g['name']) ?></a>
        <?php endforeach; ?>
      </div>
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

    <?php if (!empty($c['anniversaries'])): ?>
    <div class="detail-group">
      <div class="detail-label">Anniversary</div>
      <?php foreach ($c['anniversaries'] as $ann): ?>
      <div class="detail-value"><?= $_ctrl->e($ann) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($c['nickname'] ?? ''): ?>
    <div class="detail-group">
      <div class="detail-label">Nickname</div>
      <div class="detail-value"><?= $_ctrl->e($c['nickname']) ?></div>
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

    <?php if (!empty($c['urls'])): ?>
    <div class="detail-group">
      <div class="detail-label">Website</div>
      <?php foreach ($c['urls'] as $u): ?>
      <div class="detail-value">
        <a href="<?= $_ctrl->e($u['value']) ?>" target="_blank" rel="noopener noreferrer"><?= $_ctrl->e($u['value']) ?></a>
        <?php if ($u['type']): ?><span class="badge badge-muted"><?= $_ctrl->e($u['type']) ?></span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($c['social_profiles'])): ?>
    <div class="detail-group">
      <div class="detail-label">Social</div>
      <?php foreach ($c['social_profiles'] as $sp): ?>
      <div class="detail-value">
        <?php
        $val = $_ctrl->e($sp['value']);
        $isUrl = str_starts_with($sp['value'], 'http://') || str_starts_with($sp['value'], 'https://');
        ?>
        <?php if ($isUrl): ?>
        <a href="<?= $val ?>" target="_blank" rel="noopener noreferrer"><?= $val ?></a>
        <?php else: ?><?= $val ?><?php endif; ?>
        <?php if ($sp['type'] && $sp['type'] !== 'other'): ?>
        <span class="badge badge-muted"><?= $_ctrl->e($sp['type']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($c['custom_fields'])): ?>
    <?php foreach ($c['custom_fields'] as $cf): ?>
    <div class="detail-group">
      <div class="detail-label"><?= $_ctrl->e($cf['label'] ?? 'Custom') ?></div>
      <div class="detail-value"><?= $_ctrl->e($cf['value'] ?? '') ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($c['related'])): ?>
    <div class="detail-group">
      <div class="detail-label">Related</div>
      <?php foreach ($c['related'] as $rel): ?>
      <div class="detail-value">
        <?= $_ctrl->e($rel['name'] ?? '') ?>
        <span class="badge badge-muted" style="margin-left:4px"><?= $_ctrl->e($rel['type'] ?? '') ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- QR Code -->
<div class="card mt-md">
  <div class="card-header">
    <span class="card-title">vCard QR Code</span>
    <button type="button" class="btn btn-ghost btn-sm" id="qr-toggle">Show QR</button>
  </div>
  <div id="qr-section" style="display:none;padding:var(--spacing-md);text-align:center">
    <div id="qr-canvas" style="display:inline-block"></div>
    <p class="text-xs text-muted mt-sm">Scan to add this contact on any device</p>
  </div>
</div>

<?php if (!empty($allGroups)): ?>
<!-- Assign Groups -->
<div class="card mt-md">
  <div class="card-header">
    <span class="card-title">Groups / Labels</span>
  </div>
  <div style="padding:var(--spacing-md)">
    <form method="POST" action="/contacts/<?= (int) $c['id'] ?>" class="flex gap-sm flex-wrap align-center">
      <input type="hidden" name="_csrf"       value="<?= $_ctrl->e($csrf) ?>">
      <input type="hidden" name="_method"     value="PUT">
      <?php foreach ($c as $field => $fieldVal): ?>
        <?php if ($field === 'groups') continue; ?>
      <?php endforeach; ?>
      <span class="text-sm text-muted">Current:
        <?php if (empty($c['groups'])): ?>None<?php endif; ?>
        <?php foreach ($c['groups'] as $g): ?>
        <span class="badge" style="background:<?= $_ctrl->e($g['color'] ?: 'var(--color-sparkle-purple)') ?>;color:#fff"><?= $_ctrl->e($g['name']) ?></span>
        <?php endforeach; ?>
      </span>
      <a href="/contacts/<?= (int) $c['id'] ?>/edit#groups" class="btn btn-ghost btn-sm">Edit Groups</a>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($history)): ?>
<!-- Activity Log -->
<div class="card mt-md">
  <div class="card-header">
    <span class="card-title">Activity Log</span>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Action</th>
          <th>Detail</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td><span class="badge badge-muted"><?= $_ctrl->e($h['action']) ?></span></td>
          <td class="text-sm"><?= $_ctrl->e($h['detail']) ?></td>
          <td class="text-sm text-muted"><?= $_ctrl->e($h['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSrmMzvUKfX6LNx5bFMkWokVISsTq7es2dkA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function() {
  var btn     = document.getElementById('qr-toggle');
  var section = document.getElementById('qr-section');
  var canvas  = document.getElementById('qr-canvas');
  var qrGenerated = false;
  var vcardText = <?= json_encode($vcardText) ?>;

  if (btn && section && canvas) {
    btn.addEventListener('click', function() {
      if (section.style.display === 'none') {
        section.style.display = 'block';
        btn.textContent = 'Hide QR';
        if (!qrGenerated) {
          try {
            new QRCode(canvas, {
              text: vcardText,
              width: 220,
              height: 220,
              correctLevel: QRCode.CorrectLevel.M
            });
            qrGenerated = true;
          } catch(e) {
            canvas.innerHTML = '<p class="text-sm text-muted">QR generation failed. vCard may be too large.</p>';
          }
        }
      } else {
        section.style.display = 'none';
        btn.textContent = 'Show QR';
      }
    });
  }
}());
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $c['fn'] ?: 'Contact';
require __DIR__ . '/../../templates/layout.php';
