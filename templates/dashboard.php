<?php
/** @var \Cardy\WebUI\Controller $_ctrl */
$davUrl = \Cardy\Config::get('app.dav_url', 'http://localhost');
ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Welcome back, <?= $_ctrl->e($user['display_name'] ?: $user['username']) ?>!</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $_ctrl->e($flash['type']) ?>"><?= $_ctrl->e($flash['message']) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
    </div>
    <div class="stat-value"><?= (int) $contactCount ?></div>
    <div class="stat-label">Contacts</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>
    <div class="stat-value"><?= (int) $upcomingCount ?></div>
    <div class="stat-label">Upcoming Events</div>
  </div>
</div>

<!-- Quick actions -->
<div class="flex gap-sm mb-lg flex-wrap">
  <a href="/contacts/new" class="btn btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    New Contact
  </a>
  <a href="/calendar/new" class="btn btn-secondary">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:16px;height:16px">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
    </svg>
    New Event
  </a>
  <a href="/contacts" class="btn btn-ghost">View Contacts</a>
  <a href="/calendar" class="btn btn-ghost">View Calendar</a>
</div>

<!-- Upcoming events -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Upcoming Events</span>
    <a href="/calendar" class="btn btn-ghost btn-sm">View Calendar</a>
  </div>
  <?php if (empty($upcoming)): ?>
    <div class="empty-state" style="padding:var(--spacing-lg)">
      <div class="empty-state-icon">📅</div>
      <h3>No upcoming events</h3>
      <p class="text-muted">Add your first event to get started.</p>
      <a href="/calendar/new" class="btn btn-primary mt-sm">Add Event</a>
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Time</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcoming as $event): ?>
          <tr>
            <td><?= $_ctrl->e($event['summary'] ?: '(no title)') ?></td>
            <td><?= $_ctrl->e($event['start_date']) ?></td>
            <td><?= $event['all_day'] ? 'All day' : $_ctrl->e($event['start_time']) ?></td>
            <td>
              <a href="/calendar/<?= (int) $event['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Upcoming birthdays -->
<?php if (!empty($upcomingBirthdays)): ?>
<div class="card mt-md">
  <div class="card-header">
    <span class="card-title">🎂 Upcoming Birthdays</span>
    <a href="/contacts?sort=birthday" class="btn btn-ghost btn-sm">View Contacts</a>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Contact</th>
          <th>Date</th>
          <th>Days Away</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($upcomingBirthdays as $b): ?>
        <tr>
          <td><?= $_ctrl->e($b['fn'] ?: 'Unknown') ?></td>
          <td><?= $_ctrl->e($b['birthday_date']) ?></td>
          <td>
            <?php if ($b['days_until'] === 0): ?>
            <span class="badge badge-gold">Today! 🎉</span>
            <?php elseif ($b['days_until'] === 1): ?>
            <span class="badge badge-purple">Tomorrow</span>
            <?php else: ?>
            <span class="badge badge-muted">in <?= (int) $b['days_until'] ?> days</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="/contacts/<?= (int) $b['id'] ?>" class="btn btn-ghost btn-sm">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- DAV connection info -->
<div class="dav-info mt-lg">
  <div class="dav-info-title">📡 DAV Client Connection Settings</div>
  <p class="text-sm text-muted mb-sm">Select your device or client to see step-by-step setup instructions:</p>

  <div style="max-width:320px;margin-bottom:var(--spacing-sm)">
    <select class="form-control" id="dav-client-select">
      <option value="">— Select a client —</option>
      <option value="android">Android (DAVx⁵)</option>
      <option value="iphone">iPhone / iPad (iOS)</option>
      <option value="thunderbird">Thunderbird (CardBook / Lightning)</option>
      <option value="generic">Generic CardDAV / CalDAV Client</option>
    </select>
  </div>

  <div class="form-row">
    <div>
      <div class="form-label">CardDAV URL</div>
      <div class="dav-url" id="dav-carddav-url"><?= $_ctrl->e($davUrl) ?>/addressbooks/<?= $_ctrl->e($user['username']) ?>/default/</div>
    </div>
    <div>
      <div class="form-label">CalDAV URL</div>
      <div class="dav-url" id="dav-caldav-url"><?= $_ctrl->e($davUrl) ?>/calendars/<?= $_ctrl->e($user['username']) ?>/default/</div>
    </div>
  </div>
  <p class="text-xs text-muted mt-sm">Username: <strong><?= $_ctrl->e($user['username']) ?></strong> — use your Cardy password to authenticate.</p>

  <!-- Per-client instructions (hidden until a client is selected) -->
  <div id="dav-instructions" style="display:none;margin-top:var(--spacing-md)">

    <!-- Android / DAVx5 -->
    <div id="dav-android" class="dav-client-steps" style="display:none">
      <p class="text-sm" style="font-weight:600;margin-bottom:var(--spacing-xs)">Android — DAVx⁵ Setup</p>
      <ol style="padding-left:1.4rem;font-size:var(--text-sm);line-height:2">
        <li>Install <strong>DAVx⁵</strong> from the Play Store or F-Droid.</li>
        <li>Open DAVx⁵ → tap <strong>+</strong> → choose <em>"Login with URL and username"</em>.</li>
        <li>Enter the <strong>CardDAV URL</strong> shown above as the Base URL.</li>
        <li>Enter your Cardy <strong>username</strong> and <strong>password</strong>, then tap <em>Login</em>.</li>
        <li>DAVx⁵ will auto-discover your address books and calendars — select the ones you want to sync.</li>
        <li>Open your device's <strong>Contacts</strong> and <strong>Calendar</strong> apps — they will sync automatically.</li>
      </ol>
      <p class="text-xs text-muted mt-sm">Tip: Enable <em>WiFi only</em> sync in DAVx⁵ settings to save mobile data.</p>
    </div>

    <!-- iPhone / iOS -->
    <div id="dav-iphone" class="dav-client-steps" style="display:none">
      <p class="text-sm" style="font-weight:600;margin-bottom:var(--spacing-xs)">iPhone / iPad — iOS Setup</p>
      <p class="text-sm" style="font-weight:500;margin-bottom:4px">Contacts (CardDAV):</p>
      <ol style="padding-left:1.4rem;font-size:var(--text-sm);line-height:2;margin-bottom:var(--spacing-sm)">
        <li>Go to <strong>Settings → Contacts → Accounts → Add Account → Other</strong>.</li>
        <li>Tap <em>"Add CardDAV Account"</em>.</li>
        <li>Server: paste the <strong>CardDAV URL</strong> shown above.</li>
        <li>Enter your Cardy <strong>username</strong> and <strong>password</strong>, then tap <em>Next</em>.</li>
        <li>iOS will verify the account — tap <em>Save</em>. Your contacts will now sync.</li>
      </ol>
      <p class="text-sm" style="font-weight:500;margin-bottom:4px">Calendar (CalDAV):</p>
      <ol style="padding-left:1.4rem;font-size:var(--text-sm);line-height:2">
        <li>Go to <strong>Settings → Calendar → Accounts → Add Account → Other</strong>.</li>
        <li>Tap <em>"Add CalDAV Account"</em>.</li>
        <li>Server: paste the <strong>CalDAV URL</strong> shown above.</li>
        <li>Enter your Cardy <strong>username</strong> and <strong>password</strong>, then tap <em>Next</em>.</li>
        <li>Tap <em>Save</em>. Your calendar will now sync.</li>
      </ol>
    </div>

    <!-- Thunderbird -->
    <div id="dav-thunderbird" class="dav-client-steps" style="display:none">
      <p class="text-sm" style="font-weight:600;margin-bottom:var(--spacing-xs)">Thunderbird — CardBook &amp; Lightning Setup</p>
      <p class="text-sm" style="font-weight:500;margin-bottom:4px">Contacts (CardBook add-on):</p>
      <ol style="padding-left:1.4rem;font-size:var(--text-sm);line-height:2;margin-bottom:var(--spacing-sm)">
        <li>Install the <strong>CardBook</strong> add-on from Thunderbird's Add-on Manager.</li>
        <li>Open CardBook → <em>Address Book → New Address Book → Remote → CardDAV</em>.</li>
        <li>URL: paste the <strong>CardDAV URL</strong> shown above.</li>
        <li>Enter your Cardy <strong>username</strong> and <strong>password</strong>, then click <em>Validate</em>.</li>
        <li>Click <em>Next</em> and finish the wizard. CardBook will sync your contacts.</li>
      </ol>
      <p class="text-sm" style="font-weight:500;margin-bottom:4px">Calendar (Lightning / built-in):</p>
      <ol style="padding-left:1.4rem;font-size:var(--text-sm);line-height:2">
        <li>In the Calendar tab, right-click the calendar list → <em>New Calendar → On the Network</em>.</li>
        <li>Choose <strong>CalDAV</strong> and paste the <strong>CalDAV URL</strong> shown above.</li>
        <li>Enter your Cardy <strong>username</strong> and <strong>password</strong> when prompted.</li>
        <li>Click <em>Finish</em>. Your calendar events will sync.</li>
      </ol>
    </div>

    <!-- Generic -->
    <div id="dav-generic" class="dav-client-steps" style="display:none">
      <p class="text-sm" style="font-weight:600;margin-bottom:var(--spacing-xs)">Generic CardDAV / CalDAV Client</p>
      <p class="text-sm text-muted mb-sm">Use these settings in any standard CardDAV or CalDAV client:</p>
      <table style="font-size:var(--text-sm);border-collapse:collapse;width:100%">
        <tr><td style="padding:6px 12px 6px 0;color:var(--color-sparkle-purple);white-space:nowrap">CardDAV Server URL</td><td style="padding:6px 0" id="generic-carddav"></td></tr>
        <tr><td style="padding:6px 12px 6px 0;color:var(--color-sparkle-purple);white-space:nowrap">CalDAV Server URL</td><td style="padding:6px 0" id="generic-caldav"></td></tr>
        <tr><td style="padding:6px 12px 6px 0;color:var(--color-sparkle-purple)">Username</td><td style="padding:6px 0"><?= $_ctrl->e($user['username']) ?></td></tr>
        <tr><td style="padding:6px 12px 6px 0;color:var(--color-sparkle-purple)">Password</td><td style="padding:6px 0">Your Cardy password</td></tr>
        <tr><td style="padding:6px 12px 6px 0;color:var(--color-sparkle-purple)">Authentication</td><td style="padding:6px 0">HTTP Basic</td></tr>
      </table>
    </div>

  </div><!-- /#dav-instructions -->
</div><!-- /.dav-info -->

<script>
(function () {
  var select = document.getElementById('dav-client-select');
  var panel  = document.getElementById('dav-instructions');
  if (!select || !panel) { return; }

  // Populate the generic table once
  var carddavUrl = document.getElementById('dav-carddav-url') ? document.getElementById('dav-carddav-url').textContent.trim() : '';
  var caldavUrl  = document.getElementById('dav-caldav-url')  ? document.getElementById('dav-caldav-url').textContent.trim()  : '';
  var gc = document.getElementById('generic-carddav');
  var gd = document.getElementById('generic-caldav');
  if (gc) { gc.textContent = carddavUrl; }
  if (gd) { gd.textContent = caldavUrl; }

  select.addEventListener('change', function () {
    var val = this.value;
    document.querySelectorAll('.dav-client-steps').forEach(function (el) {
      el.style.display = 'none';
    });
    if (!val) {
      panel.style.display = 'none';
      return;
    }
    var target = document.getElementById('dav-' + val);
    if (target) {
      panel.style.display = 'block';
      target.style.display = 'block';
    }
  });
}());
</script>

<?php
$content  = ob_get_clean();
$pageTitle = 'Dashboard';
require __DIR__ . '/layout.php';
