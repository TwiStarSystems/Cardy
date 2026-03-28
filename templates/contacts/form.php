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
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="nickname">Nickname</label>
      <input class="form-control" type="text" id="nickname" name="nickname"
             value="<?= $_ctrl->e($c['nickname'] ?? '') ?>" placeholder="Johnny">
    </div>
    <div class="form-group">
      <label class="form-label" for="birthday">Birthday</label>
      <input class="form-control" type="date" id="birthday" name="birthday"
             value="<?= $_ctrl->e($c['birthday'] ?? '') ?>">
    </div>
  </div>

  <!-- Websites -->
  <div class="form-section-title">Websites</div>
  <div id="url-list">
    <?php foreach (($c['urls'] ?? []) as $u): ?>
    <div class="form-row url-row">
      <div class="form-group" style="flex:2">
        <label class="form-label">URL</label>
        <input class="form-control" type="url" name="url[]"
               value="<?= $_ctrl->e($u['value'] ?? '') ?>" placeholder="https://example.com">
      </div>
      <div class="form-group" style="flex:1">
        <label class="form-label">Type</label>
        <select class="form-control" name="url_type[]">
          <?php foreach (['', 'home', 'work', 'other'] as $t): ?>
          <option value="<?= $t ?>" <?= ($u['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t === '' ? 'Web' : ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="max-width:140px;align-self:flex-end;">
        <button type="button" class="btn btn-ghost remove-url">Remove</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="add-url" class="btn btn-secondary btn-sm">Add Website</button>

  <!-- Social Profiles -->
  <div class="form-section-title">Social Profiles</div>
  <div id="social-list">
    <?php foreach (($c['social_profiles'] ?? []) as $sp): ?>
    <div class="form-row social-row">
      <div class="form-group" style="flex:1">
        <label class="form-label">Network</label>
        <select class="form-control" name="social_type[]">
          <?php foreach (['twitter', 'linkedin', 'instagram', 'facebook', 'github', 'youtube', 'mastodon', 'other'] as $t): ?>
          <option value="<?= $t ?>" <?= ($sp['type'] ?? 'other') === $t ? 'selected' : '' ?>><?= ucfirst($t === 'twitter' ? 'X / Twitter' : $t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="flex:2">
        <label class="form-label">Username / URL</label>
        <input class="form-control" type="text" name="social_value[]"
               value="<?= $_ctrl->e($sp['value'] ?? '') ?>" placeholder="@handle or https://…">
      </div>
      <div class="form-group" style="max-width:140px;align-self:flex-end;">
        <button type="button" class="btn btn-ghost remove-social">Remove</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="add-social" class="btn btn-secondary btn-sm">Add Social Profile</button>

  <!-- Anniversaries -->
  <div class="form-section-title">Anniversaries</div>
  <div id="anniversary-list">
    <?php foreach (($c['anniversaries'] ?? []) as $ann): ?>
    <div class="form-row anniversary-row">
      <div class="form-group">
        <label class="form-label">Date</label>
        <input class="form-control" type="date" name="anniversary[]"
               value="<?= $_ctrl->e($ann) ?>">
      </div>
      <div class="form-group" style="max-width:140px;align-self:flex-end;">
        <button type="button" class="btn btn-ghost remove-anniversary">Remove</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="add-anniversary" class="btn btn-secondary btn-sm">Add Anniversary</button>

  <!-- Custom Fields -->
  <div class="form-section-title">Custom Fields</div>
  <div id="custom-list">
    <?php foreach (($c['custom_fields'] ?? []) as $cf): ?>
    <div class="form-row custom-row">
      <div class="form-group" style="flex:1">
        <label class="form-label">Label</label>
        <input class="form-control" type="text" name="custom_label[]"
               value="<?= $_ctrl->e($cf['label'] ?? '') ?>" placeholder="Field name">
      </div>
      <div class="form-group" style="flex:2">
        <label class="form-label">Value</label>
        <input class="form-control" type="text" name="custom_value[]"
               value="<?= $_ctrl->e($cf['value'] ?? '') ?>" placeholder="Value">
      </div>
      <div class="form-group" style="max-width:140px;align-self:flex-end;">
        <button type="button" class="btn btn-ghost remove-custom">Remove</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="add-custom" class="btn btn-secondary btn-sm">Add Custom Field</button>

  <!-- Related Contacts -->
  <div class="form-section-title" style="margin-top:var(--spacing-md)">Related Contacts</div>
  <div id="related-list">
    <?php foreach (($c['related'] ?? []) as $rel): ?>
    <div class="form-row related-row">
      <div class="form-group" style="flex:1">
        <label class="form-label">Relationship</label>
        <select class="form-control" name="related_type[]">
          <?php foreach (['spouse','partner','child','parent','sibling','friend','colleague','manager','assistant','relative','other'] as $rtype): ?>
          <option value="<?= $rtype ?>" <?= ($rel['type'] ?? 'other') === $rtype ? 'selected' : '' ?>><?= ucfirst($rtype) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="flex:2">
        <label class="form-label">Name</label>
        <input class="form-control" type="text" name="related_name[]" value="<?= $_ctrl->e($rel['name'] ?? '') ?>" placeholder="Full name">
      </div>
      <div class="form-group" style="max-width:140px;align-self:flex-end;">
        <button type="button" class="btn btn-ghost remove-related">Remove</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" id="add-related" class="btn btn-secondary btn-sm">Add Related Contact</button>

  <!-- Notes -->
  <div class="form-section-title" style="margin-top:var(--spacing-md)">Notes</div>
  <div class="form-group">
    <label class="form-label" for="note">Notes</label>
    <textarea class="form-control" id="note" name="note" rows="3"
              placeholder="Additional notes…"><?= $_ctrl->e($c['note'] ?? '') ?></textarea>
  </div>

  <!-- Starred & Groups -->
  <div class="form-section-title" id="groups" style="margin-top:var(--spacing-md)">Starred &amp; Groups</div>
  <div class="form-group">
    <label class="flex align-center gap-sm" style="cursor:pointer">
      <input type="checkbox" name="is_starred" value="1" <?= !empty($c['is_starred']) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--color-sparkle-purple)">
      <span>★ Starred — pin this contact to your starred view</span>
    </label>
  </div>
  <?php if (!empty($allGroups)): ?>
  <div class="form-group">
    <label class="form-label">Groups / Labels</label>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
      <?php foreach ($allGroups as $g): ?>
      <?php $checked = in_array((int) $g['id'], array_column($c['groups'] ?? [], 'id'), true); ?>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:var(--text-sm)">
        <input type="checkbox" name="groups[]" value="<?= (int) $g['id'] ?>" <?= $checked ? 'checked' : '' ?> style="accent-color:<?= $_ctrl->e($g['color'] ?: '#7c3aed') ?>">
        <span class="badge" style="background:<?= $_ctrl->e($g['color'] ?: 'var(--color-sparkle-purple)') ?>;color:#fff;pointer-events:none"><?= $_ctrl->e($g['name']) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <div class="form-hint">
      <a href="/contacts/groups" target="_blank">Manage groups</a>
    </div>
  </div>
  <?php endif; ?>

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

<template id="url-row-template">
  <div class="form-row url-row">
    <div class="form-group" style="flex:2">
      <label class="form-label">URL</label>
      <input class="form-control" type="url" name="url[]" placeholder="https://example.com">
    </div>
    <div class="form-group" style="flex:1">
      <label class="form-label">Type</label>
      <select class="form-control" name="url_type[]">
        <option value="">Web</option>
        <option value="home">Home</option>
        <option value="work">Work</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="form-group" style="max-width:140px;align-self:flex-end;">
      <button type="button" class="btn btn-ghost remove-url">Remove</button>
    </div>
  </div>
</template>

<template id="social-row-template">
  <div class="form-row social-row">
    <div class="form-group" style="flex:1">
      <label class="form-label">Network</label>
      <select class="form-control" name="social_type[]">
        <option value="twitter">X / Twitter</option>
        <option value="linkedin">Linkedin</option>
        <option value="instagram">Instagram</option>
        <option value="facebook">Facebook</option>
        <option value="github">Github</option>
        <option value="youtube">Youtube</option>
        <option value="mastodon">Mastodon</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="form-group" style="flex:2">
      <label class="form-label">Username / URL</label>
      <input class="form-control" type="text" name="social_value[]" placeholder="@handle or https://…">
    </div>
    <div class="form-group" style="max-width:140px;align-self:flex-end;">
      <button type="button" class="btn btn-ghost remove-social">Remove</button>
    </div>
  </div>
</template>

<template id="anniversary-row-template">
  <div class="form-row anniversary-row">
    <div class="form-group">
      <label class="form-label">Date</label>
      <input class="form-control" type="date" name="anniversary[]">
    </div>
    <div class="form-group" style="max-width:140px;align-self:flex-end;">
      <button type="button" class="btn btn-ghost remove-anniversary">Remove</button>
    </div>
  </div>
</template>

<template id="custom-row-template">
  <div class="form-row custom-row">
    <div class="form-group" style="flex:1">
      <label class="form-label">Label</label>
      <input class="form-control" type="text" name="custom_label[]" placeholder="Field name">
    </div>
    <div class="form-group" style="flex:2">
      <label class="form-label">Value</label>
      <input class="form-control" type="text" name="custom_value[]" placeholder="Value">
    </div>
    <div class="form-group" style="max-width:140px;align-self:flex-end;">
      <button type="button" class="btn btn-ghost remove-custom">Remove</button>
    </div>
  </div>
</template>

<template id="related-row-template">
  <div class="form-row related-row">
    <div class="form-group" style="flex:1">
      <label class="form-label">Relationship</label>
      <select class="form-control" name="related_type[]">
        <option value="spouse">Spouse</option>
        <option value="partner">Partner</option>
        <option value="child">Child</option>
        <option value="parent">Parent</option>
        <option value="sibling">Sibling</option>
        <option value="friend">Friend</option>
        <option value="colleague">Colleague</option>
        <option value="manager">Manager</option>
        <option value="assistant">Assistant</option>
        <option value="relative">Relative</option>
        <option value="other" selected>Other</option>
      </select>
    </div>
    <div class="form-group" style="flex:2">
      <label class="form-label">Name</label>
      <input class="form-control" type="text" name="related_name[]" placeholder="Full name">
    </div>
    <div class="form-group" style="max-width:140px;align-self:flex-end;">
      <button type="button" class="btn btn-ghost remove-related">Remove</button>
    </div>
  </div>
</template>

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
  const urlList   = document.getElementById('url-list');
  const socialList = document.getElementById('social-list');
  const anniversaryList = document.getElementById('anniversary-list');
  const customList = document.getElementById('custom-list');
  const relatedList = document.getElementById('related-list');

  const addEmailBtn       = document.getElementById('add-email');
  const addPhoneBtn       = document.getElementById('add-phone');
  const addUrlBtn         = document.getElementById('add-url');
  const addSocialBtn      = document.getElementById('add-social');
  const addAnniversaryBtn = document.getElementById('add-anniversary');
  const addCustomBtn      = document.getElementById('add-custom');
  const addRelatedBtn     = document.getElementById('add-related');

  const emailCounter = document.getElementById('email-counter');
  const phoneCounter = document.getElementById('phone-counter');

  const emailTpl       = document.getElementById('email-row-template');
  const phoneTpl       = document.getElementById('phone-row-template');
  const urlTpl         = document.getElementById('url-row-template');
  const socialTpl      = document.getElementById('social-row-template');
  const anniversaryTpl = document.getElementById('anniversary-row-template');
  const customTpl      = document.getElementById('custom-row-template');
  const relatedTpl     = document.getElementById('related-row-template');

  function addRow(list, tpl) {
    list.appendChild(tpl.content.firstElementChild.cloneNode(true));
  }

  function removeRow(list, selector, btn) {
    const row = btn.closest(selector);
    if (row) { row.remove(); }
  }

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
    addRow(emailList, emailTpl);
    updateButtonState();
  });

  addPhoneBtn.addEventListener('click', () => {
    if (phoneList.querySelectorAll('.phone-row').length >= MAX_ENTRIES) return;
    addRow(phoneList, phoneTpl);
    updateButtonState();
  });

  addUrlBtn.addEventListener('click', () => addRow(urlList, urlTpl));
  addSocialBtn.addEventListener('click', () => addRow(socialList, socialTpl));
  addAnniversaryBtn.addEventListener('click', () => addRow(anniversaryList, anniversaryTpl));
  addCustomBtn.addEventListener('click', () => addRow(customList, customTpl));
  addRelatedBtn.addEventListener('click', () => addRow(relatedList, relatedTpl));

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

  urlList.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-url')) removeRow(urlList, '.url-row', e.target);
  });
  socialList.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-social')) removeRow(socialList, '.social-row', e.target);
  });
  anniversaryList.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-anniversary')) removeRow(anniversaryList, '.anniversary-row', e.target);
  });
  customList.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-custom')) removeRow(customList, '.custom-row', e.target);
  });
  relatedList.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-related')) removeRow(relatedList, '.related-row', e.target);
  });

  updateButtonState();
})();
</script>

<?php
$content   = ob_get_clean();
$pageTitle = $isEdit ? 'Edit Contact' : 'New Contact';
require __DIR__ . '/../../templates/layout.php';
