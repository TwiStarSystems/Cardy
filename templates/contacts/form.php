<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
ob_start();
$isEdit  = ($contact !== null);
$action  = $isEdit ? '/contacts/' . (int) $contact['id'] : '/contacts';
$c       = $contact ?? [];
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= $isEdit ? 'Edit Contact' : 'New Contact' ?><?php if ($isEdit): ?> <span class="text-muted" style="font-size:var(--text-sm)">#<?= (int) ($c['id'] ?? 0) ?></span><?php endif; ?></h1>
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
<form method="POST" action="<?= $action ?>" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?= $_ctrl->e($csrf) ?>">
  <?php if ($isEdit): ?>
  <input type="hidden" name="uid" value="<?= $_ctrl->e($c['uid'] ?? '') ?>">
  <?php endif; ?>

  <!-- Contact Picture -->
  <div class="form-section-title">Contact Picture</div>
  <?php if (!empty($c['photo'])): ?>
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
    <div class="contact-avatar" style="width:64px;height:64px;">
      <img src="data:<?= $_ctrl->e($c['photo_mime'] ?? 'image/jpeg') ?>;base64,<?= $_ctrl->e($c['photo']) ?>" alt="Current contact picture">
    </div>
    <label class="form-hint" style="display:flex;align-items:center;gap:8px;margin:0;">
      <input type="checkbox" name="remove_photo" value="1">
      Remove current picture
    </label>
  </div>
  <?php endif; ?>
  <div class="form-group">
    <label class="form-label" for="photo_file">Upload Picture</label>
    <input class="form-control" type="file" id="photo_file" name="photo_file" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,image/jpeg,image/png,image/gif,image/webp,image/bmp">
    <div class="form-hint">Supported formats: JPG, JPEG, PNG, GIF, WEBP, BMP (max 5MB).</div>
  </div>

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
  <?php $emails = $c['emails'] ?? [['address' => '', 'type' => 'internet']]; ?>
  <div class="form-hint" id="email-counter">0/100 emails</div>
  <div id="email-list">
    <?php foreach ($emails as $index => $e): ?>
    <div class="form-row email-row">
      <div class="form-group">
        <label class="form-label" for="email_<?= (int) $index ?>">Email</label>
        <input class="form-control" type="email" id="email_<?= (int) $index ?>" name="email[]"
               value="<?= $_ctrl->e($e['address'] ?? '') ?>" placeholder="email@example.com">
      </div>
      <div class="form-group">
        <label class="form-label" for="email_type_<?= (int) $index ?>">Type</label>
        <select class="form-control" id="email_type_<?= (int) $index ?>" name="email_type[]">
          <?php foreach (['internet', 'work', 'home', 'other'] as $t): ?>
          <option value="<?= $t ?>" <?= ($e['type'] ?? 'internet') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="max-width:140px;align-self:flex-end;">
        <button type="button" class="btn btn-ghost remove-email">Remove</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="add-email" class="btn btn-secondary btn-sm">Add Email</button>
  <div class="form-hint">Up to 100 email addresses.</div>

  <!-- Phone -->
  <div class="form-section-title">Phone Numbers</div>
  <?php $phones = $c['phones'] ?? [['number' => '', 'type' => 'voice']]; ?>
  <div class="form-hint" id="phone-counter">0/100 phone numbers</div>
  <div id="phone-list">
    <?php foreach ($phones as $index => $p): ?>
    <div class="form-row phone-row">
      <div class="form-group">
        <label class="form-label" for="phone_<?= (int) $index ?>">Phone</label>
        <input class="form-control" type="tel" id="phone_<?= (int) $index ?>" name="phone[]"
               value="<?= $_ctrl->e($p['number'] ?? '') ?>" placeholder="+1 555 000 0000">
      </div>
      <div class="form-group">
        <label class="form-label" for="phone_type_<?= (int) $index ?>">Type</label>
        <select class="form-control" id="phone_type_<?= (int) $index ?>" name="phone_type[]">
          <?php foreach (['voice', 'cell', 'work', 'home', 'fax', 'other'] as $t): ?>
          <option value="<?= $t ?>" <?= ($p['type'] ?? 'voice') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="max-width:140px;align-self:flex-end;">
        <button type="button" class="btn btn-ghost remove-phone">Remove</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="add-phone" class="btn btn-secondary btn-sm">Add Phone</button>
  <div class="form-hint">Up to 100 phone numbers.</div>

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

<template id="email-row-template">
  <div class="form-row email-row">
    <div class="form-group">
      <label class="form-label">Email</label>
      <input class="form-control" type="email" name="email[]" placeholder="email@example.com">
    </div>
    <div class="form-group">
      <label class="form-label">Type</label>
      <select class="form-control" name="email_type[]">
        <option value="internet">Internet</option>
        <option value="work">Work</option>
        <option value="home">Home</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="form-group" style="max-width:140px;align-self:flex-end;">
      <button type="button" class="btn btn-ghost remove-email">Remove</button>
    </div>
  </div>
</template>

<template id="phone-row-template">
  <div class="form-row phone-row">
    <div class="form-group">
      <label class="form-label">Phone</label>
      <input class="form-control" type="tel" name="phone[]" placeholder="+1 555 000 0000">
    </div>
    <div class="form-group">
      <label class="form-label">Type</label>
      <select class="form-control" name="phone_type[]">
        <option value="voice">Voice</option>
        <option value="cell">Cell</option>
        <option value="work">Work</option>
        <option value="home">Home</option>
        <option value="fax">Fax</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="form-group" style="max-width:140px;align-self:flex-end;">
      <button type="button" class="btn btn-ghost remove-phone">Remove</button>
    </div>
  </div>
</template>

<script>
(() => {
  const MAX_ENTRIES = 100;

  const emailList = document.getElementById('email-list');
  const phoneList = document.getElementById('phone-list');
  const addEmailBtn = document.getElementById('add-email');
  const addPhoneBtn = document.getElementById('add-phone');
  const emailCounter = document.getElementById('email-counter');
  const phoneCounter = document.getElementById('phone-counter');
  const emailTpl = document.getElementById('email-row-template');
  const phoneTpl = document.getElementById('phone-row-template');

  const updateButtonState = () => {
    const emailCount = emailList.querySelectorAll('.email-row').length;
    const phoneCount = phoneList.querySelectorAll('.phone-row').length;
    addEmailBtn.disabled = emailCount >= MAX_ENTRIES;
    addPhoneBtn.disabled = phoneCount >= MAX_ENTRIES;
    emailCounter.textContent = `${emailCount}/100 emails`;
    phoneCounter.textContent = `${phoneCount}/100 phone numbers`;
  };

  addEmailBtn.addEventListener('click', () => {
    if (emailList.querySelectorAll('.email-row').length >= MAX_ENTRIES) return;
    emailList.appendChild(emailTpl.content.firstElementChild.cloneNode(true));
    updateButtonState();
  });

  addPhoneBtn.addEventListener('click', () => {
    if (phoneList.querySelectorAll('.phone-row').length >= MAX_ENTRIES) return;
    phoneList.appendChild(phoneTpl.content.firstElementChild.cloneNode(true));
    updateButtonState();
  });

  emailList.addEventListener('click', (event) => {
    if (!event.target.classList.contains('remove-email')) return;
    const rows = emailList.querySelectorAll('.email-row');
    if (rows.length <= 1) return;
    event.target.closest('.email-row')?.remove();
    updateButtonState();
  });

  phoneList.addEventListener('click', (event) => {
    if (!event.target.classList.contains('remove-phone')) return;
    const rows = phoneList.querySelectorAll('.phone-row');
    if (rows.length <= 1) return;
    event.target.closest('.phone-row')?.remove();
    updateButtonState();
  });

  updateButtonState();
})();
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $isEdit ? 'Edit Contact' : 'New Contact';
require __DIR__ . '/../../templates/layout.php';
