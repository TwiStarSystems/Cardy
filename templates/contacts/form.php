<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$isEdit  = ($contact !== null);
$action  = $isEdit ? '/contacts/' . (int) $contact['id'] : '/contacts';
$c       = $contact ?? [];
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Edit Contact' : 'New Contact' ?></h1>
    <?php if ($isEdit): ?>
    <p class="page-subtitle"><?= $_ctrl->e($c['fn'] ?? '') ?></p>
    <?php endif; ?>
  </div>
  <?php if ($isEdit): ?>
  <a href="/contacts/<?= (int) $c['id'] ?>" class="btn btn-secondary">Cancel</a>
  <?php else: ?>
  <a href="/contacts" class="btn btn-secondary">Cancel</a>
  <?php endif; ?>
</div>

<div class="card">
<form method="POST" action="<?= $action ?>">
  <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
  <?php if ($isEdit): ?>
  <input type="hidden" name="uid" value="<?= $_ctrl->e($c['uid'] ?? '') ?>">
  <?php endif; ?>

  <!-- Basic Info -->
  <div class="form-section-title">Basic Information</div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="first_name">First Name</label>
      <input class="form-control" type="text" id="first_name" name="first_name"
             value="<?= $_ctrl->e($c['first_name'] ?? '') ?>" placeholder="John">
    </div>
    <div class="form-group">
      <label class="form-label" for="last_name">Last Name</label>
      <input class="form-control" type="text" id="last_name" name="last_name"
             value="<?= $_ctrl->e($c['last_name'] ?? '') ?>" placeholder="Doe">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="org">Organization</label>
      <input class="form-control" type="text" id="org" name="org"
             value="<?= $_ctrl->e($c['org'] ?? '') ?>" placeholder="Acme Corp">
    </div>
    <div class="form-group">
      <label class="form-label" for="title">Job Title</label>
      <input class="form-control" type="text" id="title" name="title"
             value="<?= $_ctrl->e($c['title'] ?? '') ?>" placeholder="Software Engineer">
    </div>
  </div>

  <!-- Email -->
  <div class="form-section-title">Email Addresses</div>
  <?php
  $emails = $c['emails'] ?? [];
  while (count($emails) < 3) { $emails[] = ['address' => '', 'type' => 'internet']; }
  ?>
  <?php foreach ([1, 2, 3] as $i): ?>
  <?php $e = $emails[$i - 1]; ?>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="email<?= $i ?>">Email <?= $i ?></label>
      <input class="form-control" type="email" id="email<?= $i ?>" name="email<?= $i ?>"
             value="<?= $_ctrl->e($e['address'] ?? '') ?>" placeholder="email@example.com">
    </div>
    <div class="form-group">
      <label class="form-label" for="email<?= $i - 1 ?>_type">Type</label>
      <select class="form-control" id="email<?= $i - 1 ?>_type" name="email<?= $i - 1 ?>_type">
        <?php foreach (['internet', 'work', 'home', 'other'] as $t): ?>
        <option value="<?= $t ?>" <?= ($e['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Phone -->
  <div class="form-section-title">Phone Numbers</div>
  <?php
  $phones = $c['phones'] ?? [];
  while (count($phones) < 3) { $phones[] = ['number' => '', 'type' => 'voice']; }
  ?>
  <?php foreach ([1, 2, 3] as $i): ?>
  <?php $p = $phones[$i - 1]; ?>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="phone<?= $i ?>">Phone <?= $i ?></label>
      <input class="form-control" type="tel" id="phone<?= $i ?>" name="phone<?= $i ?>"
             value="<?= $_ctrl->e($p['number'] ?? '') ?>" placeholder="+1 555 000 0000">
    </div>
    <div class="form-group">
      <label class="form-label" for="phone<?= $i - 1 ?>_type">Type</label>
      <select class="form-control" id="phone<?= $i - 1 ?>_type" name="phone<?= $i - 1 ?>_type">
        <?php foreach (['voice', 'cell', 'work', 'home', 'fax', 'other'] as $t): ?>
        <option value="<?= $t ?>" <?= ($p['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Address -->
  <div class="form-section-title">Home Address</div>
  <?php $home = array_values(array_filter($c['addresses'] ?? [], fn($a) => $a['type'] === 'home'))[0] ?? []; ?>
  <div class="form-group">
    <label class="form-label" for="home_street">Street</label>
    <input class="form-control" type="text" id="home_street" name="home_street"
           value="<?= $_ctrl->e($home['street'] ?? '') ?>" placeholder="123 Main St">
  </div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="home_city">City</label>
      <input class="form-control" type="text" id="home_city" name="home_city"
             value="<?= $_ctrl->e($home['city'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="home_region">State/Region</label>
      <input class="form-control" type="text" id="home_region" name="home_region"
             value="<?= $_ctrl->e($home['region'] ?? '') ?>">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="home_postcode">Postal Code</label>
      <input class="form-control" type="text" id="home_postcode" name="home_postcode"
             value="<?= $_ctrl->e($home['postcode'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="home_country">Country</label>
      <input class="form-control" type="text" id="home_country" name="home_country"
             value="<?= $_ctrl->e($home['country'] ?? '') ?>">
    </div>
  </div>

  <!-- Work Address -->
  <div class="form-section-title">Work Address</div>
  <?php $work = array_values(array_filter($c['addresses'] ?? [], fn($a) => $a['type'] === 'work'))[0] ?? []; ?>
  <div class="form-group">
    <label class="form-label" for="work_street">Street</label>
    <input class="form-control" type="text" id="work_street" name="work_street"
           value="<?= $_ctrl->e($work['street'] ?? '') ?>" placeholder="456 Office Blvd">
  </div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="work_city">City</label>
      <input class="form-control" type="text" id="work_city" name="work_city"
             value="<?= $_ctrl->e($work['city'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="work_region">State/Region</label>
      <input class="form-control" type="text" id="work_region" name="work_region"
             value="<?= $_ctrl->e($work['region'] ?? '') ?>">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="work_postcode">Postal Code</label>
      <input class="form-control" type="text" id="work_postcode" name="work_postcode"
             value="<?= $_ctrl->e($work['postcode'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="work_country">Country</label>
      <input class="form-control" type="text" id="work_country" name="work_country"
             value="<?= $_ctrl->e($work['country'] ?? '') ?>">
    </div>
  </div>

  <!-- Other -->
  <div class="form-section-title">Other</div>
  <div class="form-group">
    <label class="form-label" for="birthday">Birthday</label>
    <input class="form-control" type="date" id="birthday" name="birthday"
           value="<?= $_ctrl->e($c['birthday'] ?? '') ?>">
  </div>
  <div class="form-group">
    <label class="form-label" for="note">Notes</label>
    <textarea class="form-control" id="note" name="note" rows="3"
              placeholder="Additional notes…"><?= $_ctrl->e($c['note'] ?? '') ?></textarea>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">
      <?= $isEdit ? 'Save Changes' : 'Create Contact' ?>
    </button>
    <?php if ($isEdit): ?>
    <a href="/contacts/<?= (int) $c['id'] ?>" class="btn btn-secondary">Cancel</a>
    <form method="POST" action="/contacts/<?= (int) $c['id'] ?>/delete" style="display:inline;margin-left:auto">
      <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
      <button type="submit" class="btn btn-danger"
              onclick="return confirm('Delete this contact? This cannot be undone.')">
        Delete Contact
      </button>
    </form>
    <?php endif; ?>
  </div>
</form>
</div>

<?php
$content   = ob_get_clean();
$pageTitle = $isEdit ? 'Edit Contact' : 'New Contact';
require __DIR__ . '/../../templates/layout.php';
