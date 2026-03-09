<?php
declare(strict_types=1);

namespace Cardy\WebUI;

/**
 * Base controller — shared helpers for all WebUI controllers.
 */
class Controller
{
    // -------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------

    protected function render(string $template, array $data = []): void
    {
        $data['_ctrl'] = $this;
        extract($data);
        $path = __DIR__ . '/../../templates/' . ltrim($template, '/') . '.php';
        if (!file_exists($path)) {
            http_response_code(500);
            echo "Template not found: {$template}";
            return;
        }
        require $path;
    }

    protected function redirect(string $url): never
    {
        header("Location: {$url}");
        exit;
    }

    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        $this->render('error', ['code' => $code, 'message' => $message]);
        exit;
    }

    // -------------------------------------------------------
    // Auth guards
    // -------------------------------------------------------

    protected function requireAuth(): array
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        return $_SESSION['user'];
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireAuth();
        if (!$this->isAdmin($user)) {
            $this->abort(403, 'Access denied. Administrator privileges required.');
        }
        return $user;
    }

    protected function isAdmin(array $user): bool
    {
        if (!empty($user['role'])) {
            return $user['role'] === 'admin';
        }
        return !empty($user['is_admin']);
    }

    // -------------------------------------------------------
    // CSRF
    // -------------------------------------------------------

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrf(): void
    {
        $token    = $_POST['_csrf'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!$expected || !hash_equals($expected, $token)) {
            $this->abort(403, 'Invalid or missing CSRF token.');
        }
    }

    // -------------------------------------------------------
    // Utility
    // -------------------------------------------------------

    /** HTML-escape a string. */
    public function e(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Flash a message to the next request. */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /** Consume a flash message. */
    public function getFlash(): ?array
    {
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}
