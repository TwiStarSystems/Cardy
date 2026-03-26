<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
// Helper: first non-empty value
$pick = static fn($a, $b) => $a !== '' && $a !== null ? $a : $b;
?>

<div class="page-header">
  <div>
    <a href="/contacts/duplicates" class="text-muted text-sm">← Find Duplicates</a>
    <h1 class="page-title" style="margin-top:4px">Merge Contacts</h1>
    <p class="page-subtitle">Review and combine two contacts into one</p>
  </div>
</div>

<!-- Side-by-side comparison -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--spacing-md);margin-bottom:var(--spacing-lg)">
  <?php foreach ([['keep', $keep], ['discard', $other]] as [$role, $cx]): ?>
  <div class="card" style="border: <?= $role === 'keep' ? '2px solid var(--color-sparkle-purple)' : '1px solid var(--color-border)' ?>">
    <div class="card-header">
      <span class="card-title"><?= $role === 'keep' ? '✓ Keep (surviving)' : '✗ Discard (will be deleted)' ?></span>
    </div>
    <div style="padding:var(--spacing-md)">
      <p><strong><?= $_ctrl->e($cx['fn'] ?: 'Unknown') ?></strong> <span class="text-muted text-xs">#<?= (int) $cx['id'] ?></span></p>
      <?php if ($cx['org']): ?><p class="text-sm"><?= $_ctrl->e($cx['org']) ?></p><?php endif; ?>
      <?php if ($cx['email']): ?><p class="text-sm">✉ <?= $_ctrl->e($cx['email']) ?></p><?php endif; ?>
      <?php if ($cx['phone']): ?><p class="text-sm">📞 <?= $_ctrl->e($cx['phone']) ?></p><?php endif; ?>
      <?php if ($cx['birthday']): ?><p class="text-sm">🎂 <?= $_ctrl->e($cx['birthday']) ?></p><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Merge form -->
<div class="card">
  <div class="card-header"><span class="card-title">Merged Result</span></div>
  <div style="padding:var(--spacing-xs) var(--spacing-md) var(--spacing-sm)">
    <p class="text-sm text-muted">Edit the merged contact below. Click <strong>Use ↓</strong> buttons to copy a value from either contact.</p>
  </div>

  <form method="POST" action="/contacts/<?= (int) $keep['id'] ?>/merge" enctype="multipart/form-data">
    <input type="hidden" name="_csrf"       value="<?= $_ctrl->e($csrf) ?>">
    <input type="hidden" name="discard_id"  value="<?= (int) $other['id'] ?>">
    <input type="hidden" name="uid"         value="<?= $_ctrl->e($keep['uid'] ?? '') ?>">

    <div style="padding:0 var(--spacing-md) var(--spacing-md)">

      <!-- Quick-fill buttons -->
      <div class="flex gap-sm mb-md flex-wrap">
        <button type="button" class="btn btn-secondary btn-sm" onclick="fillFrom('keep')">
          Fill all from #<?= (int) $keep['id'] ?>
        </button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="fillFrom('discard')">
          Fill all from #<?= (int) $other['id'] ?>
        </button>
      </div>

      <!-- Name row -->
      <div class="form-section-title">Name</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name</label>
          <div class="flex gap-xs mb-xs">
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('first_name','<?= addslashes($_ctrl->e($keep['first_name'])) ?>')">
              <?= $_ctrl->e($keep['first_name'] ?: '—') ?> ↓
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('first_name','<?= addslashes($_ctrl->e($other['first_name'])) ?>')">
              <?= $_ctrl->e($other['first_name'] ?: '—') ?> ↓
            </button>
          </div>
          <input type="text" name="first_name" id="f_first_name" class="form-control"
                 value="<?= $_ctrl->e($pick($keep['first_name'], $other['first_name'])) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Last Name</label>
          <div class="flex gap-xs mb-xs">
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('last_name','<?= addslashes($_ctrl->e($keep['last_name'])) ?>')">
              <?= $_ctrl->e($keep['last_name'] ?: '—') ?> ↓
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('last_name','<?= addslashes($_ctrl->e($other['last_name'])) ?>')">
              <?= $_ctrl->e($other['last_name'] ?: '—') ?> ↓
            </button>
          </div>
          <input type="text" name="last_name" id="f_last_name" class="form-control"
                 value="<?= $_ctrl->e($pick($keep['last_name'], $other['last_name'])) ?>">
        </div>
      </div>

      <!-- Organisation / Job title -->
      <div class="form-section-title">Organisation</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Organisation</label>
          <div class="flex gap-xs mb-xs">
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('org','<?= addslashes($_ctrl->e($keep['org'])) ?>')">
              <?= $_ctrl->e($keep['org'] ?: '—') ?> ↓
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('org','<?= addslashes($_ctrl->e($other['org'])) ?>')">
              <?= $_ctrl->e($other['org'] ?: '—') ?> ↓
            </button>
          </div>
          <input type="text" name="org" id="f_org" class="form-control"
                 value="<?= $_ctrl->e($pick($keep['org'], $other['org'])) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Job Title</label>
          <div class="flex gap-xs mb-xs">
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('title','<?= addslashes($_ctrl->e($keep['title'])) ?>')">
              <?= $_ctrl->e($keep['title'] ?: '—') ?> ↓
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('title','<?= addslashes($_ctrl->e($other['title'])) ?>')">
              <?= $_ctrl->e($other['title'] ?: '—') ?> ↓
            </button>
          </div>
          <input type="text" name="title" id="f_title" class="form-control"
                 value="<?= $_ctrl->e($pick($keep['title'], $other['title'])) ?>">
        </div>
      </div>

      <!-- Birthday / Nickname -->
      <div class="form-section-title">Additional Info</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Birthday</label>
          <div class="flex gap-xs mb-xs">
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('birthday','<?= addslashes($_ctrl->e($keep['birthday'])) ?>')">
              <?= $_ctrl->e($keep['birthday'] ?: '—') ?> ↓
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('birthday','<?= addslashes($_ctrl->e($other['birthday'])) ?>')">
              <?= $_ctrl->e($other['birthday'] ?: '—') ?> ↓
            </button>
          </div>
          <input type="date" name="birthday" id="f_birthday" class="form-control"
                 value="<?= $_ctrl->e($pick($keep['birthday'], $other['birthday'])) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Nickname</label>
          <div class="flex gap-xs mb-xs">
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('nickname','<?= addslashes($_ctrl->e($keep['nickname'] ?? '')) ?>')">
              <?= $_ctrl->e($keep['nickname'] ?? '' ?: '—') ?> ↓
            </button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="setField('nickname','<?= addslashes($_ctrl->e($other['nickname'] ?? '')) ?>')">
              <?= $_ctrl->e($other['nickname'] ?? '' ?: '—') ?> ↓
            </button>
          </div>
          <input type="text" name="nickname" id="f_nickname" class="form-control"
                 value="<?= $_ctrl->e($pick($keep['nickname'] ?? '', $other['nickname'] ?? '')) ?>">
        </div>
      </div>

      <!-- Emails -->
      <div class="form-section-title">Email Addresses</div>
      <p class="text-sm text-muted mb-sm">Emails from both contacts are pre-filled (duplicates removed).</p>
      <?php
      $mergedEmails = [];
      $seenAddrs = [];
      foreach (array_merge($keep['emails'] ?? [], $other['emails'] ?? []) as $em) {
          $key = strtolower(trim($em['address']));
          if ($key !== '' && !isset($seenAddrs[$key])) {
              $seenAddrs[$key] = true;
              $mergedEmails[] = $em;
          }
      }
      foreach ($mergedEmails as $i => $em):
      ?>
      <div class="form-row mb-xs">
        <div class="form-group">
          <input type="email" name="email[]" class="form-control" value="<?= $_ctrl->e($em['address']) ?>">
        </div>
        <div class="form-group" style="max-width:140px">
          <select name="email_type[]" class="form-control">
            <?php foreach (['internet', 'work', 'home', 'other'] as $t): ?>
            <option value="<?= $t ?>" <?= ($em['type'] ?? 'internet') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Phones -->
      <div class="form-section-title">Phone Numbers</div>
      <?php
      $mergedPhones = [];
      $seenPhones = [];
      foreach (array_merge($keep['phones'] ?? [], $other['phones'] ?? []) as $ph) {
          $key = preg_replace('/[^0-9+]/', '', $ph['number']);
          if ($key !== '' && !isset($seenPhones[$key])) {
              $seenPhones[$key] = true;
              $mergedPhones[] = $ph;
          }
      }
      foreach ($mergedPhones as $ph):
      ?>
      <div class="form-row mb-xs">
        <div class="form-group">
          <input type="tel" name="phone[]" class="form-control" value="<?= $_ctrl->e($ph['number']) ?>">
        </div>
        <div class="form-group" style="max-width:140px">
          <select name="phone_type[]" class="form-control">
            <?php foreach (['voice', 'cell', 'work', 'home', 'fax', 'other'] as $t): ?>
            <option value="<?= $t ?>" <?= ($ph['type'] ?? 'voice') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Notes -->
      <div class="form-section-title">Notes</div>
      <div class="flex gap-xs mb-xs flex-wrap">
        <?php if ($keep['note']): ?>
        <button type="button" class="btn btn-ghost btn-sm" onclick="setField('note','<?= addslashes(htmlspecialchars($keep['note'], ENT_QUOTES)) ?>')">
          Use note from #<?= (int) $keep['id'] ?> ↓
        </button>
        <?php endif; ?>
        <?php if ($other['note']): ?>
        <button type="button" class="btn btn-ghost btn-sm" onclick="setField('note','<?= addslashes(htmlspecialchars($other['note'], ENT_QUOTES)) ?>')">
          Use note from #<?= (int) $other['id'] ?> ↓
        </button>
        <?php endif; ?>
      </div>
      <textarea name="note" id="f_note" class="form-control" rows="3"><?= $_ctrl->e($pick($keep['note'], $other['note'])) ?></textarea>

      <div class="flex gap-sm mt-lg">
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('Merge these contacts? Contact #<?= (int) $other['id'] ?> will be permanently deleted.')">
          Merge Contacts
        </button>
        <a href="/contacts/duplicates" class="btn btn-ghost">Cancel</a>
      </div>
    </div>
  </form>
</div>

<script>
var keepData    = <?= json_encode($keep, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
var discardData = <?= json_encode($other, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function setField(name, value) {
  var el = document.getElementById('f_' + name);
  if (el) { el.value = value; }
}

function fillFrom(role) {
  var src = role === 'keep' ? keepData : discardData;
  ['first_name','last_name','org','title','birthday','nickname','note'].forEach(function(f) {
    var el = document.getElementById('f_' + f);
    if (el && src[f]) { el.value = src[f]; }
  });
}
</script>

<?php
$content   = ob_get_clean();
$pageTitle = 'Merge Contacts';
require __DIR__ . '/../../templates/layout.php';
