<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\User;
use Cardy\WebUI\Controller;

class AdminController extends Controller
{
    private function configPath(): string
    {
        return __DIR__ . '/../../../config/config.php';
    }

    public function users(): void
    {
        $this->requireAdmin();
        $this->render('admin/users', [
            'users' => User::all(),
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
            $this->flash('success', "User '{$username}' created successfully.");
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

        User::update((int) $params['id'], $email, $displayName, $role);

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

        User::delete((int) $params['id']);
        $this->flash('success', 'User deleted.');
        $this->redirect('/admin/users');
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
                'webui_url'       => \Cardy\Config::get('app.webui_url', 'http://localhost'),
                'dav_url'         => \Cardy\Config::get('app.dav_url', 'http://localhost'),
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
        $webuiUrl = rtrim(trim((string) ($_POST['webui_url'] ?? 'http://localhost')), '/');
        $davUrl = rtrim(trim((string) ($_POST['dav_url'] ?? 'http://localhost')), '/');
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

        if (!preg_match('#^https?://#i', $webuiUrl) || !preg_match('#^https?://#i', $davUrl)) {
            $this->flash('error', 'Web UI URL and DAV URL must start with http:// or https://');
            $this->redirect('/admin/server');
            return;
        }

        $config['app']['name'] = $name;
        $config['app']['timezone'] = $timezone;
        $config['app']['webui_url'] = $webuiUrl;
        $config['app']['dav_url'] = $davUrl;
        $config['app']['trusted_proxies'] = $trustedProxies;

        $php = "<?php\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($path, $php, LOCK_EX);

        \Cardy\Config::load($path);

        $this->flash('success', 'Server settings updated successfully.');
        $this->redirect('/admin/server');
    }
}
