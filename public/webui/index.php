<?php
declare(strict_types=1);

/**
 * Cardy Web UI — entry point
 * Served by Nginx on port 80 (unified with DAV).
 */

require __DIR__ . '/../../vendor/autoload.php';

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(503);
    echo '<h1>Cardy is not configured</h1><p>Please run <code>install.sh</code> or copy <code>config/config.php.example</code> to <code>config/config.php</code> and fill in the database credentials.</p>';
    exit;
}

\Cardy\Config::load($configPath);

\Cardy\Http\TrustedProxy::apply((array) \Cardy\Config::get('app.trusted_proxies', ['127.0.0.1', '::1']));

// Start session
$appName = \Cardy\Config::get('app.name', 'Cardy');
session_name('cardy_session');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
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
        '/contacts/export'                => [ContactsController::class, 'export'],
        '/contacts/import'                => [ContactsController::class, 'importForm'],
        '/contacts/new'                   => [ContactsController::class, 'create'],
        '/contacts/groups'                => [ContactsController::class, 'groups'],
        '/contacts/duplicates'            => [ContactsController::class, 'duplicates'],
        '/contacts/{id}'                  => [ContactsController::class, 'view'],
        '/contacts/{id}/edit'             => [ContactsController::class, 'edit'],
        '/contacts/{id}/merge'            => [ContactsController::class, 'mergeForm'],
        '/calendar'                       => [CalendarController::class, 'index'],
        '/calendar/new'                   => [CalendarController::class, 'create'],
        '/calendar/week'                  => [CalendarController::class, 'week'],
        '/calendar/day'                   => [CalendarController::class, 'dayView'],
        '/calendar/agenda'                => [CalendarController::class, 'agenda'],
        '/calendar/export'                => [CalendarController::class, 'exportCalendar'],
        '/calendar/import'                => [CalendarController::class, 'importCalendarForm'],
        '/calendar/{id}/edit'             => [CalendarController::class, 'edit'],
        '/admin/users'                    => [AdminController::class,   'users'],
        '/admin/users/new'                => [AdminController::class,   'createUser'],
        '/admin/users/{id}/edit'          => [AdminController::class,   'editUser'],
        '/admin/server'                   => [AdminController::class,   'serverSettings'],
    ],
    'POST' => [
        '/login'                          => [AuthController::class,    'processLogin'],
        '/contacts'                       => [ContactsController::class, 'store'],
        '/contacts/import'                => [ContactsController::class, 'import'],
        '/contacts/bulk'                  => [ContactsController::class, 'bulkAction'],
        '/contacts/groups'                => [ContactsController::class, 'createGroup'],
        '/contacts/groups/{id}'           => [ContactsController::class, 'updateGroup'],
        '/contacts/groups/{id}/delete'    => [ContactsController::class, 'deleteGroup'],
        '/contacts/addressbooks'          => [ContactsController::class, 'createAddressBookAction'],
        '/contacts/addressbooks/{id}/switch' => [ContactsController::class, 'switchAddressBook'],
        '/contacts/addressbooks/{id}/rename' => [ContactsController::class, 'renameAddressBookAction'],
        '/contacts/addressbooks/{id}/delete' => [ContactsController::class, 'deleteAddressBookAction'],
        '/contacts/{id}'                  => [ContactsController::class, 'update'],
        '/contacts/{id}/delete'           => [ContactsController::class, 'delete'],
        '/contacts/{id}/star'             => [ContactsController::class, 'toggleStar'],
        '/contacts/{id}/ignore-duplicate' => [ContactsController::class, 'toggleIgnoreDuplicate'],
        '/contacts/{id}/merge'            => [ContactsController::class, 'mergeSubmit'],
        '/calendar'                       => [CalendarController::class, 'store'],
        '/calendar/import'                => [CalendarController::class, 'importCalendarAction'],
        '/calendar/calendars'             => [CalendarController::class, 'createCalendarAction'],
        '/calendar/calendars/{id}/switch' => [CalendarController::class, 'switchCalendar'],
        '/calendar/calendars/{id}/rename' => [CalendarController::class, 'renameCalendarAction'],
        '/calendar/calendars/{id}/delete' => [CalendarController::class, 'deleteCalendarAction'],
        '/calendar/{id}'                  => [CalendarController::class, 'update'],
        '/calendar/{id}/delete'           => [CalendarController::class, 'delete'],
        '/admin/users'                    => [AdminController::class,   'storeUser'],
        '/admin/users/{id}'               => [AdminController::class,   'updateUser'],
        '/admin/users/{id}/delete'        => [AdminController::class,   'deleteUser'],
        '/admin/server'                   => [AdminController::class,   'updateServerSettings'],
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
