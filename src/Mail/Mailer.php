<?php
declare(strict_types=1);

namespace Cardy\Mail;

use Cardy\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Mailer
{
    public static function isConfigured(): bool
    {
        return !empty(Config::get('mail.host')) && !empty(Config::get('mail.from_address'));
    }

    /**
     * Send a plain-text email. Throws \RuntimeException on failure.
     */
    public static function send(string $toAddress, string $toName, string $subject, string $body): void
    {
        if (!self::isConfigured()) {
            throw new \RuntimeException('Mail is not configured. Set mail.host and mail.from_address in server settings.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host    = (string) Config::get('mail.host');
        $mail->Port    = (int) Config::get('mail.port', 587);

        $username = (string) Config::get('mail.username', '');
        $password = (string) Config::get('mail.password', '');
        if ($username !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
        }

        $encryption = strtolower((string) Config::get('mail.encryption', 'tls'));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $fromAddress = (string) Config::get('mail.from_address');
        $fromName    = (string) Config::get('mail.from_name', 'Cardy');
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($toAddress, $toName);

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        $mail->send();
    }

    // -------------------------------------------------------
    // Typed email helpers
    // -------------------------------------------------------

    public static function sendPasswordReset(string $toAddress, string $displayName, string $resetUrl): void
    {
        $appName = (string) Config::get('app.name', 'Cardy');
        $body = <<<TEXT
Hi {$displayName},

Someone requested a password reset for your {$appName} account.

Click the link below to set a new password (valid for 1 hour):

  {$resetUrl}

If you did not request this, you can safely ignore this email — your password has not been changed.

— {$appName}
TEXT;
        self::send($toAddress, $displayName, "Reset your {$appName} password", $body);
    }

    public static function sendWelcome(string $toAddress, string $displayName, string $username, string $loginUrl): void
    {
        $appName = (string) Config::get('app.name', 'Cardy');
        $body = <<<TEXT
Hi {$displayName},

An account has been created for you on {$appName}.

Username: {$username}
Sign in at: {$loginUrl}

If you didn't expect this email, contact your administrator.

— {$appName}
TEXT;
        self::send($toAddress, $displayName, "Your {$appName} account is ready", $body);
    }

    public static function sendQuotaExceeded(string $toAddress, string $displayName, string $type, int $quota): void
    {
        $appName = (string) Config::get('app.name', 'Cardy');
        $label = $type === 'contact' ? 'contacts' : 'calendar events';
        $body = <<<TEXT
Hi {$displayName},

Your {$appName} account has reached its {$label} limit ({$quota}).

No new {$label} can be added until your administrator increases your quota or you delete some existing {$label}.

— {$appName}
TEXT;
        self::send($toAddress, $displayName, "Your {$appName} {$label} quota is full", $body);
    }

    public static function sendTest(string $toAddress, string $displayName): void
    {
        $appName = (string) Config::get('app.name', 'Cardy');
        $body = <<<TEXT
Hi {$displayName},

This is a test email from {$appName}. If you received this, your SMTP settings are working correctly.

— {$appName}
TEXT;
        self::send($toAddress, $displayName, "{$appName} — SMTP test", $body);
    }
}
