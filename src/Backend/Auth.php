<?php
declare(strict_types=1);

namespace Cardy\Backend;

use Sabre\DAV\Auth\Backend\AbstractBasic;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Cardy\Database;

/**
 * HTTP Basic Auth backend that validates against the Cardy users table
 * using bcrypt / argon2 password hashes.
 */
class Auth extends AbstractBasic
{
    protected $realm = 'Cardy';

    public function __construct(string $realm = 'Cardy')
    {
        $this->realm = $realm;
    }

    protected function validateUserPass($username, $password)
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        return password_verify($password, $row['password_hash']);
    }
}
