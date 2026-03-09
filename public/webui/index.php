<?php
declare(strict_types=1);

/**
 * Cardy Web UI — entry point
 * Served by Nginx on port 8321.
 */

require __DIR__ . '/../../vendor/autoload.php';

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(503);
    echo '<h1>Cardy is not configured</h1><p>Please run <code>install.sh</code> or copy <code>config/config.php.example</code> to <code>config/config.php</code> and fill in the database credentials.</p>';
    exit;
}

\Cardy\Config::load($configPath);

// Start session
$appName = \Cardy\Config::get('app.name', 'Cardy');
session_name('cardy_session');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

// -------------------------------------------------------
// Simple front-controller router
// -------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = '/' . trim($uri, '/');

// Serve static assets directly (Nginx should handle these, but just in case)
if (preg_match('/\.(css|js|png|jpg|ico|woff2?)$/', $uri)) {
    return false;
}

use Cardy\WebUI\Controllers\AuthController;
use Cardy\WebUI\Controllers\DashboardController;
use Cardy\WebUI\Controllers\ContactsController;
use Cardy\WebUI\Controllers\CalendarController;
use Cardy\WebUI\Controllers\AdminController;

$routes = [
    'GET'  => [
        '/'                               => [AuthController::class,    'showLogin'],
        '/login'                          => [AuthController::class,    'showLogin'],
        '/logout'                         => [AuthController::class,    'logout'],
        '/dashboard'                      => [DashboardController::class, 'index'],
        '/contacts'                       => [ContactsController::class, 'index'],
        '/contacts/new'                   => [ContactsController::class, 'create'],
        '/contacts/{id}'                  => [ContactsController::class, 'view'],
        '/contacts/{id}/edit'             => [ContactsController::class, 'edit'],
        '/calendar'                       => [CalendarController::class, 'index'],
        '/calendar/new'                   => [CalendarController::class, 'create'],
        '/calendar/{id}/edit'             => [CalendarController::class, 'edit'],
        '/admin/users'                    => [AdminController::class,   'users'],
        '/admin/users/new'                => [AdminController::class,   'createUser'],
        '/admin/users/{id}/edit'          => [AdminController::class,   'editUser'],
    ],
    'POST' => [
        '/login'                          => [AuthController::class,    'processLogin'],
        '/contacts'                       => [ContactsController::class, 'store'],
        '/contacts/{id}'                  => [ContactsController::class, 'update'],
        '/contacts/{id}/delete'           => [ContactsController::class, 'delete'],
        '/calendar'                       => [CalendarController::class, 'store'],
        '/calendar/{id}'                  => [CalendarController::class, 'update'],
        '/calendar/{id}/delete'           => [CalendarController::class, 'delete'],
        '/admin/users'                    => [AdminController::class,   'storeUser'],
        '/admin/users/{id}'               => [AdminController::class,   'updateUser'],
        '/admin/users/{id}/delete'        => [AdminController::class,   'deleteUser'],
    ],
];

/**
 * Convert a route pattern to a regex and extract named parameters.
 */
function matchRoute(string $pattern, string $uri): false|array
{
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (!preg_match($regex, $uri, $matches)) {
        return false;
    }
    return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
}

$methodRoutes = $routes[$method] ?? [];
$matched = false;

foreach ($methodRoutes as $pattern => $handler) {
    $params = matchRoute($pattern, $uri);
    if ($params !== false) {
        [$class, $action] = $handler;
        try {
            $controller = new $class();
            $controller->$action($params);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo '<h1>Database Error</h1><p>Unable to connect to the database. Please check your configuration.</p>';
            if (\Cardy\Config::get('app.debug')) {
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            }
        }
        $matched = true;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    require __DIR__ . '/../../templates/error.php';
}
