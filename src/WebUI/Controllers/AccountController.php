<?php
declare(strict_types=1);

namespace Cardy\WebUI\Controllers;

use Cardy\Models\AppPassword;
use Cardy\Models\AuditLog;
use Cardy\Models\User;
use Cardy\Totp;
use Cardy\WebUI\Controller;

class AccountController extends Controller
{
    public function security(): void
    {
        $user     = $this->requireAuth();
        $fullUser = User::findById($user['id']);

        $this->render('account/security', [
            'user'         => $user,
            'totpEnabled'  => !empty($fullUser['totp_enabled']),
            'totpSecret'   => $_SESSION['totp_pending_secret'] ?? null,
            'passwords'    => AppPassword::forUser($user['username']),
            'newToken'     => $_SESSION['new_app_token'] ?? null,
            'csrf'         => $this->csrfToken(),
            'flash'        => $this->getFlash(),
        ]);
        unset($_SESSION['new_app_token']);
    }

    // -------------------------------------------------------
    // TOTP
    // -------------------------------------------------------

    public function totpSetup(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $_SESSION['totp_pending_secret'] = Totp::generateSecret();
        $this->redirect('/account');
    }

    public function totpConfirm(): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $secret = $_SESSION['totp_pending_secret'] ?? '';
        $code   = trim($_POST['code'] ?? '');

        if (!$secret || !Totp::verify($secret, $code)) {
            $this->flash('error', 'Invalid code — please try again with your authenticator app.');
            $this->redirect('/account');
        }

        User::enableTotp($user['id'], $secret);
        unset($_SESSION['totp_pending_secret']);
        AuditLog::record($user['username'], 'totp.enabled');
        $this->flash('success', 'Two-factor authentication is now enabled.');
        $this->redirect('/account');
    }

    public function totpDisable(): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();
        User::disableTotp($user['id']);
        unset($_SESSION['totp_pending_secret']);
        AuditLog::record($user['username'], 'totp.disabled');
        $this->flash('success', 'Two-factor authentication has been disabled.');
        $this->redirect('/account');
    }

    public function totpCancel(): void
    {
        $this->requireAuth();
        unset($_SESSION['totp_pending_secret']);
        $this->redirect('/account');
    }

    // -------------------------------------------------------
    // App passwords
    // -------------------------------------------------------

    public function appPasswords(): void
    {
        $this->redirect('/account');
    }

    public function createAppPassword(): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->flash('error', 'Please provide a name for this app password.');
            $this->redirect('/account');
        }

        $label = substr($name, 0, 100);
        $token = AppPassword::create($user['username'], $label);
        $_SESSION['new_app_token'] = $token;
        AuditLog::record($user['username'], 'apppassword.create', "Label: {$label}");
        $this->redirect('/account');
    }

    public function deleteAppPassword(array $params): void
    {
        $user = $this->requireAuth();
        $this->verifyCsrf();
        AppPassword::delete((int) ($params['id'] ?? 0), $user['username']);
        AuditLog::record($user['username'], 'apppassword.delete', "ID: " . (int) ($params['id'] ?? 0));
        $this->flash('success', 'App password revoked.');
        $this->redirect('/account');
    }
}
