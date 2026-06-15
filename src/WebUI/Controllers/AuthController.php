<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\LoginAttempt;
use Cardy\Models\User;
use Cardy\Totp;
use Cardy\WebUI\Controller;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        unset($_SESSION['totp_pending_id']);
        $this->render('login', ['csrf' => $this->csrfToken()]);
    }

    public function processLogin(): void
    {
        $this->verifyCsrf();

        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (LoginAttempt::isBlocked($ip)) {
            $this->render('login', [
                'csrf'  => $this->csrfToken(),
                'error' => 'Too many failed login attempts. Please wait 15 minutes before trying again.',
            ]);
            return;
        }

        $user = User::authenticate($username, $password);
        if (!$user) {
            LoginAttempt::record($ip, $username);
            $this->render('login', [
                'csrf'     => $this->csrfToken(),
                'error'    => 'Invalid username or password.',
                'username' => $username,
            ]);
            return;
        }

        LoginAttempt::clearForIp($ip);
        session_regenerate_id(true);

        if (!empty($user['totp_enabled'])) {
            $_SESSION['totp_pending_id'] = $user['id'];
            $this->redirect('/login/totp');
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        $this->redirect('/dashboard');
    }

    public function showTotpChallenge(): void
    {
        if (empty($_SESSION['totp_pending_id'])) {
            $this->redirect('/login');
        }
        $this->render('login_totp', ['csrf' => $this->csrfToken()]);
    }

    public function verifyTotp(): void
    {
        if (empty($_SESSION['totp_pending_id'])) {
            $this->redirect('/login');
        }
        $this->verifyCsrf();

        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = (int) $_SESSION['totp_pending_id'];
        $code   = trim($_POST['code'] ?? '');

        if (LoginAttempt::isBlocked($ip)) {
            $this->render('login_totp', [
                'csrf'  => $this->csrfToken(),
                'error' => 'Too many failed attempts. Please wait 15 minutes before trying again.',
            ]);
            return;
        }

        $secret = User::getTotpSecret($userId);
        if (!$secret || !Totp::verify($secret, $code)) {
            LoginAttempt::record($ip, '');
            $this->render('login_totp', [
                'csrf'  => $this->csrfToken(),
                'error' => 'Invalid authentication code.',
            ]);
            return;
        }

        LoginAttempt::clearForIp($ip);
        $user = User::findById($userId);
        session_regenerate_id(true);
        unset($_SESSION['totp_pending_id']);
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
