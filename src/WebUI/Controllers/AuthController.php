<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\User;
use Cardy\WebUI\Controller;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        $this->render('login', ['csrf' => $this->csrfToken()]);
    }

    public function processLogin(): void
    {
        $this->verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = User::authenticate($username, $password);
        if (!$user) {
            $this->render('login', [
                'csrf'  => $this->csrfToken(),
                'error' => 'Invalid username or password.',
                'username' => $this->e($username),
            ]);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        session_destroy();
        $this->redirect('/login');
    }
}
