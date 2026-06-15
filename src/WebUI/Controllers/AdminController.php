<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Mail\Mailer;
use Cardy\Models\AuditLog;
use Cardy\Models\InviteToken;
use Cardy\Models\User;
use Cardy\WebUI\Controller;

class AdminController extends Controller
{
    private function configPath(): string
    {
        return __DIR__ . '/../../../config/config.php';
    }

    /**
     * Clean a URL submitted via a form: strip control characters (e.g. stray
     * terminal escape sequences pasted into the installer/this field) and
     * surrounding whitespace, then drop any trailing slash.
     */
    private function sanitizeUrlInput(mixed $raw): string
    {
        $value = is_string($raw) ? $raw : '';
        // Strip ANSI/terminal escape sequences first (e.g. arrow-key codes like
        // ESC[D pasted into the installer — the ESC is a control char but the
        // trailing "[D" is printable and would otherwise survive), then remove
        // any remaining control characters and surrounding whitespace.
        $value = preg_replace('/\x1B\[[0-9;?]*[ -\/]*[@-~]/', '', $value) ?? $value;
        $value = preg_replace('/\x1B./s', '', $value) ?? $value;
        $value = preg_replace('/[\x00-\x1F\x7F]+/', '', $value) ?? '';
        return rtrim(trim($value), '/');
    }

    public function users(): void
    {
        $this->requireAdmin();
        $this->render('admin/users', [
            'users' => User::allWithStats(),
            'csrf'  => $this->csrfToken(),
            'flash' => $this->getFlash(),
        ]);
    }

    public function createUser(): void
    {
        $this->requireAdmin();
        $this->render('admin/user_form', [
            'editUser' => null,
            'csrf'     => $this->csrfToken(),
        ]);
    }

    public function storeUser(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $username    = trim($_POST['username']    ?? '');
        $password    = $_POST['password']         ?? '';
        $email       = trim($_POST['email']       ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $role        = (($_POST['role'] ?? 'user') === 'admin') ? 'admin' : 'user';

        $errors = [];
        if (strlen($username) < 2) {
            $errors[] = 'Username must be at least 2 characters.';
        }
        if (!preg_match('/^[a-z0-9_\-]+$/i', $username)) {
            $errors[] = 'Username may only contain letters, numbers, hyphens and underscores.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($errors) {
            $this->render('admin/user_form', [
                'editUser' => null,
                'errors'   => $errors,
                'csrf'     => $this->csrfToken(),
                'post'     => $_POST,
            ]);
            return;
        }

        try {
            User::create($username, $password, $email, $displayName, $role);
            $admin = $_SESSION['user']['username'] ?? '';
            AuditLog::record($admin, 'admin.user.create', "Created user '{$username}' (role: {$role})");
            $this->flash('success', "User '{$username}' created successfully.");
            if ($email !== '' && Mailer::isConfigured()) {
                try {
                    $loginUrl = \Cardy\Config::get('app.webui_url', '') . '/login';
                    Mailer::sendWelcome($email, $displayName ?: $username, $username, $loginUrl);
                } catch (\Exception) {
                    // welcome email failure is non-fatal
                }
            }
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to create user: ' . $e->getMessage());
        }

        $this->redirect('/admin/users');
    }

    public function editUser(array $params): void
    {
        $this->requireAdmin();
        $editUser = User::findById((int) $params['id']);
        if (!$editUser) {
            $this->abort(404, 'User not found.');
        }

        $this->render('admin/user_form', [
            'editUser' => $editUser,
            'csrf'     => $this->csrfToken(),
        ]);
    }

    public function updateUser(array $params): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $editUser = User::findById((int) $params['id']);
        if (!$editUser) {
            $this->abort(404, 'User not found.');
        }

        $email       = trim($_POST['email']        ?? '');
        $displayName = trim($_POST['display_name']  ?? '');
        $role        = (($_POST['role'] ?? 'user') === 'admin') ? 'admin' : 'user';

        $current = $_SESSION['user'] ?? null;
        if ($current && (int) $current['id'] === (int) $params['id'] && $role !== 'admin') {
            $this->flash('error', 'You cannot remove your own admin role.');
            $this->redirect('/admin/users/' . $params['id'] . '/edit');
            return;
        }

        $contactQuota = max(0, (int) ($_POST['contact_quota'] ?? 0));
        $eventQuota   = max(0, (int) ($_POST['event_quota']   ?? 0));

        User::update((int) $params['id'], $email, $displayName, $role);
        User::setQuotas((int) $params['id'], $contactQuota, $eventQuota);
        $admin = $_SESSION['user']['username'] ?? '';
        AuditLog::record($admin, 'admin.user.update', "Updated user '{$editUser['username']}' (role: {$role})");

        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 8) {
                $this->flash('error', 'Password must be at least 8 characters.');
                $this->redirect('/admin/users/' . $params['id'] . '/edit');
                return;
            }
            User::updatePassword((int) $params['id'], $_POST['password']);
        }

        if ($current && (int) $current['id'] === (int) $params['id']) {
            $fresh = User::findById((int) $params['id']);
            if ($fresh) {
                $_SESSION['user'] = $fresh;
            }
        }

        $this->flash('success', 'User updated successfully.');
        $this->redirect('/admin/users');
    }

    public function deleteUser(array $params): void
    {
        $admin = $this->requireAdmin();
        $this->verifyCsrf();

        if ((int) $params['id'] === (int) $admin['id']) {
            $this->flash('error', 'You cannot delete your own account.');
            $this->redirect('/admin/users');
            return;
        }

        $target = User::findById((int) $params['id']);
        User::delete((int) $params['id']);
        AuditLog::record($admin['username'], 'admin.user.delete', "Deleted user '" . ($target['username'] ?? $params['id']) . "'");
        $this->flash('success', 'User deleted.');
        $this->redirect('/admin/users');
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        $pdo = \Cardy\Database::getInstance();

        $totalUsers    = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $totalContacts = (int) $pdo->query('SELECT COUNT(*) FROM cards')->fetchColumn();
        $totalEvents   = (int) $pdo->query("SELECT COUNT(*) FROM calendarobjects WHERE componenttype = 'VEVENT'")->fetchColumn();
        $totalTasks    = (int) $pdo->query("SELECT COUNT(*) FROM calendarobjects WHERE componenttype = 'VTODO'")->fetchColumn();

        $contactBytes = (int) $pdo->query('SELECT COALESCE(SUM(LENGTH(carddata)),0) FROM cards')->fetchColumn();
        $eventBytes   = (int) $pdo->query('SELECT COALESCE(SUM(LENGTH(calendardata)),0) FROM calendarobjects')->fetchColumn();

        $this->render('admin/dashboard', [
            'users'         => User::allWithStats(),
            'totalUsers'    => $totalUsers,
            'totalContacts' => $totalContacts,
            'totalEvents'   => $totalEvents,
            'totalTasks'    => $totalTasks,
            'contactBytes'  => $contactBytes,
            'eventBytes'    => $eventBytes,
            'flash'         => $this->getFlash(),
        ]);
    }

    public function backupPage(): void
    {
        $this->requireAdmin();
        $this->render('admin/backup', [
            'flash' => $this->getFlash(),
            'csrf'  => $this->csrfToken(),
        ]);
    }

    public function backupDownload(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $pdo      = \Cardy\Database::getInstance();
        $appName  = \Cardy\Config::get('app.name', 'Cardy');
        $filename = 'cardy-backup-' . date('Y-m-d') . '.zip';

        $tmp = tempnam(sys_get_temp_dir(), 'cardy_backup_');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->flash('error', 'Could not create ZIP archive.');
            $this->redirect('/admin/backup');
            return;
        }

        $manifest = [
            'app'         => $appName,
            'exported_at' => date('c'),
            'version'     => '1',
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        // Export contacts: one combined .vcf per address book
        $books = $pdo->query(
            "SELECT ab.id, ab.uri, ab.displayname, REPLACE(ab.principaluri,'principals/','') AS username
             FROM addressbooks ab ORDER BY ab.principaluri, ab.id"
        )->fetchAll();
        foreach ($books as $book) {
            $cards = $pdo->prepare('SELECT carddata FROM cards WHERE addressbookid = ?');
            $cards->execute([$book['id']]);
            $vcf = '';
            foreach ($cards->fetchAll() as $card) {
                $vcf .= $card['carddata'] . "\r\n";
            }
            if ($vcf !== '') {
                $zip->addFromString(
                    "contacts/{$book['username']}/{$book['uri']}.vcf",
                    $vcf
                );
            }
        }

        // Export calendars: one .ics per calendar instance
        $cals = $pdo->query(
            "SELECT ci.calendarid, ci.uri, ci.displayname,
                    REPLACE(ci.principaluri,'principals/','') AS username
             FROM calendarinstances ci WHERE ci.access = 1 ORDER BY ci.principaluri, ci.calendarid"
        )->fetchAll();
        foreach ($cals as $cal) {
            $objs = $pdo->prepare('SELECT calendardata FROM calendarobjects WHERE calendarid = ?');
            $objs->execute([$cal['calendarid']]);
            $rows = $objs->fetchAll();
            if (empty($rows)) {
                continue;
            }
            $ics  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Cardy//EN\r\n";
            foreach ($rows as $obj) {
                // Strip outer VCALENDAR wrapper and concatenate components
                $inner = preg_replace('/^BEGIN:VCALENDAR.*?(\r?\n)/si', '', $obj['calendardata']);
                $inner = preg_replace('/END:VCALENDAR\s*$/si', '', $inner ?? '');
                $ics  .= trim($inner) . "\r\n";
            }
            $ics .= "END:VCALENDAR\r\n";
            $zip->addFromString(
                "calendars/{$cal['username']}/{$cal['uri']}.ics",
                $ics
            );
        }

        $zip->close();

        $admin = $_SESSION['user']['username'] ?? '';
        AuditLog::record($admin, 'admin.backup.download', 'Full backup downloaded');

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    public function invites(): void
    {
        $this->requireAdmin();
        $this->render('admin/invites', [
            'invites'  => InviteToken::all(),
            'csrf'     => $this->csrfToken(),
            'flash'    => $this->getFlash(),
            'signupBase' => rtrim((string) \Cardy\Config::get('app.webui_url', ''), '/') . '/signup?token=',
        ]);
    }

    public function createInvite(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $note     = trim($_POST['note'] ?? '');
        $maxUses  = max(1, (int) ($_POST['max_uses']    ?? 1));
        $expDays  = ($_POST['expires_in'] ?? '') !== '' ? max(1, (int) $_POST['expires_in']) : null;
        $admin    = $_SESSION['user']['username'] ?? '';

        InviteToken::create($admin, $note, $maxUses, $expDays);
        AuditLog::record($admin, 'admin.invite.create', "note={$note}, max_uses={$maxUses}");
        $this->flash('success', 'Invite link created.');
        $this->redirect('/admin/invites');
    }

    public function deleteInvite(array $params): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        InviteToken::delete((int) $params['id']);
        $admin = $_SESSION['user']['username'] ?? '';
        AuditLog::record($admin, 'admin.invite.delete', "Invite #{$params['id']} revoked");
        $this->flash('success', 'Invite revoked.');
        $this->redirect('/admin/invites');
    }

    public function mailSettings(): void
    {
        $this->requireAdmin();
        $this->render('admin/mail', [
            'csrf'  => $this->csrfToken(),
            'flash' => $this->getFlash(),
            'mail'  => [
                'host'         => (string) \Cardy\Config::get('mail.host', ''),
                'port'         => (int)    \Cardy\Config::get('mail.port', 587),
                'encryption'   => (string) \Cardy\Config::get('mail.encryption', 'tls'),
                'username'     => (string) \Cardy\Config::get('mail.username', ''),
                'from_address' => (string) \Cardy\Config::get('mail.from_address', ''),
                'from_name'    => (string) \Cardy\Config::get('mail.from_name', 'Cardy'),
            ],
        ]);
    }

    public function updateMailSettings(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $path   = $this->configPath();
        $config = require $path;

        $host        = trim((string) ($_POST['host']         ?? ''));
        $port        = max(1, min(65535, (int) ($_POST['port'] ?? 587)));
        $encryption  = in_array($_POST['encryption'] ?? '', ['tls','ssl','none'], true) ? $_POST['encryption'] : 'tls';
        $username    = trim((string) ($_POST['username']     ?? ''));
        $fromAddress = trim((string) ($_POST['from_address'] ?? ''));
        $fromName    = trim((string) ($_POST['from_name']    ?? 'Cardy'));
        $password    = $_POST['password'] ?? '';

        if ($fromName === '') {
            $fromName = 'Cardy';
        }

        if ($fromAddress !== '' && !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'From address must be a valid email address.');
            $this->redirect('/admin/mail');
            return;
        }

        $config['mail'] = [
            'host'         => $host,
            'port'         => $port,
            'encryption'   => $encryption,
            'username'     => $username,
            'password'     => $password !== '' ? $password : (string) \Cardy\Config::get('mail.password', ''),
            'from_address' => $fromAddress,
            'from_name'    => $fromName,
        ];

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $php  = "<?php\nreturn json_decode(<<<'JSON'\n{$json}\nJSON, true);\n";
        file_put_contents($path, $php, LOCK_EX);
        \Cardy\Config::load($path);

        $admin = $_SESSION['user']['username'] ?? '';
        AuditLog::record($admin, 'admin.mail.update', "host={$host}:{$port}, encryption={$encryption}");
        $this->flash('success', 'Mail settings saved.');
        $this->redirect('/admin/mail');
    }

    public function testMail(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $to = trim($_POST['test_to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Please enter a valid email address to send the test to.');
            $this->redirect('/admin/mail');
            return;
        }

        try {
            Mailer::sendTest($to, $to);
            $this->flash('success', "Test email sent to {$to}. Check your inbox.");
        } catch (\Exception $e) {
            $this->flash('error', 'Test email failed: ' . $e->getMessage());
        }
        $this->redirect('/admin/mail');
    }

    public function auditLog(): void
    {
        $this->requireAdmin();
        $filter = trim($_GET['action'] ?? '');
        $this->render('admin/audit_log', [
            'entries' => AuditLog::recent(300, $filter),
            'filter'  => $filter,
            'flash'   => $this->getFlash(),
        ]);
    }

    public function serverSettings(): void
    {
        $this->requireAdmin();
        $this->render('admin/server', [
            'csrf'  => $this->csrfToken(),
            'flash' => $this->getFlash(),
            'app'   => [
                'name'            => \Cardy\Config::get('app.name', 'Cardy'),
                'timezone'        => \Cardy\Config::get('app.timezone', 'UTC'),
                // Sanitize on display so a config with stray control characters
                // (e.g. from a bad paste during install) renders as an editable field.
                'webui_url'       => $this->sanitizeUrlInput(\Cardy\Config::get('app.webui_url', 'http://localhost')),
                'dav_url'         => $this->sanitizeUrlInput(\Cardy\Config::get('app.dav_url', 'http://localhost')),
                'trusted_proxies' => (array) \Cardy\Config::get('app.trusted_proxies', ['127.0.0.1', '::1']),
            ],
        ]);
    }

    public function updateServerSettings(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $path = $this->configPath();
        $config = require $path;

        $name = trim((string) ($_POST['name'] ?? 'Cardy'));
        $timezone = trim((string) ($_POST['timezone'] ?? 'UTC'));
        $webuiUrl = $this->sanitizeUrlInput($_POST['webui_url'] ?? '');
        $davUrl = $this->sanitizeUrlInput($_POST['dav_url'] ?? '');
        $trustedProxiesRaw = trim((string) ($_POST['trusted_proxies'] ?? '127.0.0.1,::1'));

        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', $trustedProxiesRaw))));
        if (empty($trustedProxies)) {
            $trustedProxies = ['127.0.0.1', '::1'];
        }

        if ($name === '') {
            $name = 'Cardy';
        }

        if ($timezone === '' || !in_array($timezone, timezone_identifiers_list(), true)) {
            $this->flash('error', 'Invalid timezone.');
            $this->redirect('/admin/server');
            return;
        }

        foreach (['Web UI URL' => $webuiUrl, 'DAV URL' => $davUrl] as $label => $url) {
            if (!preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->flash('error', "{$label} must be a valid http:// or https:// URL.");
                $this->redirect('/admin/server');
                return;
            }
        }

        $config['app']['name'] = $name;
        $config['app']['timezone'] = $timezone;
        $config['app']['webui_url'] = $webuiUrl;
        $config['app']['dav_url'] = $davUrl;
        $config['app']['trusted_proxies'] = $trustedProxies;

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $php  = "<?php\nreturn json_decode(<<<'JSON'\n{$json}\nJSON, true);\n";
        file_put_contents($path, $php, LOCK_EX);

        \Cardy\Config::load($path);

        $adminUser = $_SESSION['user']['username'] ?? '';
        AuditLog::record($adminUser, 'admin.server.update', "name={$name}, webui={$webuiUrl}, dav={$davUrl}");
        $this->flash('success', 'Server settings updated successfully.');
        $this->redirect('/admin/server');
    }
}
