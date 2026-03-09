<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\User;
use Cardy\WebUI\Controller;

class AdminController extends Controller
{
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
        $isAdmin     = !empty($_POST['is_admin']);

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
            User::create($username, $password, $email, $displayName, $isAdmin);
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
        $isAdmin     = !empty($_POST['is_admin']);

        User::update((int) $params['id'], $email, $displayName, $isAdmin);

        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 8) {
                $this->flash('error', 'Password must be at least 8 characters.');
                $this->redirect('/admin/users/' . $params['id'] . '/edit');
                return;
            }
            User::updatePassword((int) $params['id'], $_POST['password']);
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
}
