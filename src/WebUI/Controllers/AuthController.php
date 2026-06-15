<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Mail\Mailer;
use Cardy\Models\AuditLog;
use Cardy\Models\LoginAttempt;
use Cardy\Models\PasswordReset;
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
        unset($_SESSION['totp_pending_id'], $_SESSION['totp_pending_at']);

        $error   = null;
        $success = null;
        match ($_GET['reason'] ?? '') {
            'timeout'        => $error   = 'Your session expired due to inactivity. Please sign in again.',
            'password_reset' => $success = 'Your password has been reset. You can now sign in.',
            default          => null,
        };
        $this->render('login', [
            'csrf'    => $this->csrfToken(),
            'error'   => $error,
            'success' => $success,
            'mailConfigured' => Mailer::isConfigured(),
        ]);
    }

    public function processLogin(): void
    {
        $this->verifyCsrf();

        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (LoginAttempt::isBlocked($ip)) {
            AuditLog::record($username, 'login.blocked', "IP {$ip} rate-limited");
            $this->render('login', [
                'csrf'  => $this->csrfToken(),
                'error' => 'Too many failed login attempts. Please wait 15 minutes before trying again.',
            ]);
            return;
        }

        $user = User::authenticate($username, $password);
        if (!$user) {
            LoginAttempt::record($ip, $username);
            AuditLog::record($username, 'login.failure', 'Bad password');
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
            $_SESSION['totp_pending_at'] = time();
            AuditLog::record($user['username'], 'login.totp_required', 'Password OK, awaiting TOTP');
            $this->redirect('/login/totp');
        }

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user']          = $user;
        $_SESSION['last_activity'] = time();
        AuditLog::record($user['username'], 'login.success');
        $this->redirect('/dashboard');
    }

    public function showTotpChallenge(): void
    {
        if (empty($_SESSION['totp_pending_id'])) {
            $this->redirect('/login');
        }
        if ((time() - (int) ($_SESSION['totp_pending_at'] ?? 0)) > 300) {
            unset($_SESSION['totp_pending_id'], $_SESSION['totp_pending_at']);
            $this->redirect('/login?reason=timeout');
        }
        $this->render('login_totp', ['csrf' => $this->csrfToken()]);
    }

    public function verifyTotp(): void
    {
        if (empty($_SESSION['totp_pending_id'])) {
            $this->redirect('/login');
        }
        if ((time() - (int) ($_SESSION['totp_pending_at'] ?? 0)) > 300) {
            unset($_SESSION['totp_pending_id'], $_SESSION['totp_pending_at']);
            $this->redirect('/login?reason=timeout');
        }
        $this->verifyCsrf();

        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = (int) $_SESSION['totp_pending_id'];
        $code   = trim($_POST['code'] ?? '');

        if (LoginAttempt::isBlocked($ip)) {
            AuditLog::record('', 'login.totp_blocked', "IP {$ip} rate-limited");
            $this->render('login_totp', [
                'csrf'  => $this->csrfToken(),
                'error' => 'Too many failed attempts. Please wait 15 minutes before trying again.',
            ]);
            return;
        }

        $secret = User::getTotpSecret($userId);
        if (!$secret || !Totp::verify($secret, $code)) {
            LoginAttempt::record($ip, '');
            $user = User::findById($userId);
            AuditLog::record($user['username'] ?? '', 'login.totp_failure', 'Bad TOTP code');
            $this->render('login_totp', [
                'csrf'  => $this->csrfToken(),
                'error' => 'Invalid authentication code.',
            ]);
            return;
        }

        LoginAttempt::clearForIp($ip);
        $user = User::findById($userId);
        session_regenerate_id(true);
        unset($_SESSION['totp_pending_id'], $_SESSION['totp_pending_at']);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user']          = $user;
        $_SESSION['last_activity'] = time();
        AuditLog::record($user['username'], 'login.success', '2FA verified');
        $this->redirect('/dashboard');
    }

    public function showForgotPassword(): void
    {
        $this->render('forgot_password', [
            'csrf'           => $this->csrfToken(),
            'mailConfigured' => Mailer::isConfigured(),
        ]);
    }

    public function processForgotPassword(): void
    {
        $this->verifyCsrf();
        $email = trim($_POST['email'] ?? '');

        // Always show the same success message to prevent user enumeration.
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && Mailer::isConfigured()) {
            $user = User::findByEmail($email);
            if ($user) {
                try {
                    $token    = PasswordReset::create($user['username']);
                    $resetUrl = \Cardy\Config::get('app.webui_url', '') . '/reset-password?token=' . $token;
                    Mailer::sendPasswordReset($email, $user['display_name'] ?: $user['username'], $resetUrl);
                    AuditLog::record($user['username'], 'auth.password_reset_requested', "Reset sent to {$email}");
                } catch (\Exception) {
                    // swallow — don't reveal whether email was found
                }
            }
        }

        $this->render('forgot_password', [
            'csrf'           => $this->csrfToken(),
            'mailConfigured' => Mailer::isConfigured(),
            'success'        => 'If an account with that email exists, a reset link has been sent.',
        ]);
    }

    public function showResetPassword(): void
    {
        $token    = trim($_GET['token'] ?? '');
        $username = $token ? PasswordReset::verify($token) : null;

        $this->render('reset_password', [
            'csrf'  => $this->csrfToken(),
            'token' => $username ? $token : '',
            'error' => $username ? null : 'This reset link is invalid or has expired.',
        ]);
    }

    public function processResetPassword(): void
    {
        $this->verifyCsrf();
        $token    = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';

        $username = $token ? PasswordReset::verify($token) : null;
        if (!$username) {
            $this->render('reset_password', [
                'csrf'  => $this->csrfToken(),
                'token' => '',
                'error' => 'This reset link is invalid or has expired.',
            ]);
            return;
        }

        if (strlen($password) < 8) {
            $this->render('reset_password', [
                'csrf'  => $this->csrfToken(),
                'token' => $token,
                'error' => 'Password must be at least 8 characters.',
            ]);
            return;
        }

        if ($password !== $confirm) {
            $this->render('reset_password', [
                'csrf'  => $this->csrfToken(),
                'token' => $token,
                'error' => 'Passwords do not match.',
            ]);
            return;
        }

        $user = User::findByUsername($username);
        if ($user) {
            User::updatePassword((int) $user['id'], $password);
        }
        PasswordReset::consume($token);
        AuditLog::record($username, 'auth.password_reset_completed');

        $this->redirect('/login?reason=password_reset');
    }

    public function logout(): void
    {
        $username = $_SESSION['user']['username'] ?? '';
        AuditLog::record($username, 'logout');
        session_destroy();
        $this->redirect('/login');
    }
}
