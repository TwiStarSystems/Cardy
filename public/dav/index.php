<?php
declare(strict_types=1);

/**
 * Cardy DAV Server — entry point
 * Served by Nginx on port 80 (standard HTTP).
 *
 * Handles:
 *   /principals/    — DAV Principals
 *   /addressbooks/  — CardDAV
 *   /calendars/     — CalDAV
 */

require __DIR__ . '/../../vendor/autoload.php';

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(503);
    header('Content-Type: text/plain');
    echo "Cardy is not configured. Please run install.sh first.\n";
    exit;
}

\Cardy\Config::load($configPath);

\Cardy\Http\TrustedProxy::apply((array) \Cardy\Config::get('app.trusted_proxies', ['127.0.0.1', '::1']));

$pdo = \Cardy\Database::getInstance();

// -------------------------------------------------------
// SabreDAV backends
// -------------------------------------------------------
$authBackend      = new \Cardy\Backend\Auth(\Cardy\Config::get('app.name', 'Cardy'));
$principalBackend = new \Sabre\DAVACL\PrincipalBackend\PDO($pdo);
$cardDAVBackend   = new \Sabre\CardDAV\Backend\PDO($pdo);
$calDAVBackend    = new \Sabre\CalDAV\Backend\PDO($pdo);

// -------------------------------------------------------
// Server tree
// -------------------------------------------------------
$tree = [
    new \Sabre\DAVACL\PrincipalCollection($principalBackend),
    new \Sabre\CardDAV\AddressBookRoot($principalBackend, $cardDAVBackend),
    new \Sabre\CalDAV\CalendarRoot($principalBackend, $calDAVBackend),
];

$server = new \Sabre\DAV\Server($tree);
$server->setBaseUri('/');

// -------------------------------------------------------
// Plugins
// -------------------------------------------------------
$authPlugin = new \Sabre\DAV\Auth\Plugin($authBackend);
$server->addPlugin($authPlugin);

$server->addPlugin(new \Sabre\DAV\Browser\Plugin());
$server->addPlugin(new \Sabre\DAV\Sync\Plugin());
$server->addPlugin(new \Sabre\CardDAV\Plugin());
$server->addPlugin(new \Sabre\CalDAV\Plugin());
$server->addPlugin(new \Sabre\DAV\Sharing\Plugin());
$server->addPlugin(new \Sabre\CalDAV\Schedule\Plugin());
$server->addPlugin(new \Sabre\CalDAV\SharingPlugin());
$server->addPlugin(new \Sabre\CalDAV\Subscriptions\Plugin());
$server->addPlugin(new \Sabre\DAVACL\Plugin());

$server->exec();
