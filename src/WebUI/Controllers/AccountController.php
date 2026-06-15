<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\AppPassword;
use Cardy\WebUI\Controller;

class AccountController extends Controller
{
    public function appPasswords(): void
    {
        $user = $this->requireAuth();
        $this->render('account/app_passwords', [
            'user'         => $user,
            'passwords'    => AppPassword::forUser($user['username']),
            'newToken'     => $_SESSION['new_app_token'] ?? null,
            'csrf'         => $this->csrfToken(),
            'flash'        => $this->getFlash(),
        ]);
        unset($_SESSION['new_app_token']);
    }

    public function createAppPassword(): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flash('error', 'Please provide a name for this app password.');
            $this->redirect('/account/app-passwords');
        }
        if (strlen($name) > 100) {
            $name = substr($name, 0, 100);
        }

        $token = AppPassword::create($user['username'], $name);
        $_SESSION['new_app_token'] = $token;
        $this->redirect('/account/app-passwords');
    }

    public function deleteAppPassword(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $id = (int) ($params['id'] ?? 0);
        AppPassword::delete($id, $user['username']);
        $this->flash('success', 'App password deleted.');
        $this->redirect('/account/app-passwords');
    }
}
