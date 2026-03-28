<?php
declare(strict_types=1);

namespace Cardy\Backend;

use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Server;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * SabreDAV plugin that writes a one-line access-log entry for every DAV request.
 *
 * Log format (tab-separated):
 *   timestamp  remote_ip  username  method  uri  http_status  response_bytes
 *
 * The log file path is configured via config.php:
 *   'app' => [ 'dav_log_file' => '/var/log/cardy/dav-access.log' ]
 *
 * If the directory does not exist it is created automatically (mode 0755).
 * Logging failures are silently ignored so they never break DAV clients.
 */
class DavLogger extends ServerPlugin
{
    private string $logFile;
    private ?Server $server = null;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function initialize(Server $server): void
    {
        $this->server = $server;
        $server->on('afterMethod:*', [$this, 'logRequest'], 1000);
    }

    public function getPluginName(): string
    {
        return 'cardy-dav-logger';
    }

    public function getPluginInfo(): array
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Writes a one-line entry to the DAV access log for every request.',
        ];
    }

    public function logRequest(RequestInterface $request, ResponseInterface $response): void
    {
        try {
            $dir = dirname($this->logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $timestamp = date('Y-m-d H:i:s');
            $ip        = $_SERVER['REMOTE_ADDR'] ?? '-';
            $method    = $request->getMethod();
            $uri       = $request->getPath();
            $status    = $response->getStatus();
            $size      = strlen((string) ($response->getBody() ?? ''));

            // Attempt to resolve the authenticated username from the current principal
            $username = '-';
            if ($this->server !== null) {
                try {
                    $authPlugin = $this->server->getPlugin('auth');
                    if ($authPlugin !== null && method_exists($authPlugin, 'getCurrentPrincipal')) {
                        $principal = $authPlugin->getCurrentPrincipal();
                        if ($principal !== null) {
                            // principal URI is "principals/username"
                            $username = basename($principal);
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $line = implode("\t", [$timestamp, $ip, $username, $method, $uri, (string) $status, (string) $size]) . "\n";

            file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Never allow logging errors to disrupt DAV clients
        }
    }
}
