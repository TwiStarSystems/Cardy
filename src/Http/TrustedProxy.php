<?php
declare(strict_types=1);

namespace Cardy\Http;

final class TrustedProxy
{
    public static function apply(array $trustedProxies): void
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($remoteAddr === '' || !self::isTrusted($remoteAddr, $trustedProxies)) {
            return;
        }

        $forwardedFor = self::firstValue($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwardedFor !== '') {
            $_SERVER['REMOTE_ADDR'] = $forwardedFor;
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $_SERVER['REMOTE_ADDR'] = trim((string) $_SERVER['HTTP_X_REAL_IP']);
        }

        $forwardedHost = self::firstValue($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '');
        if ($forwardedHost !== '') {
            $_SERVER['HTTP_HOST'] = $forwardedHost;
            $_SERVER['SERVER_NAME'] = preg_replace('/:\\d+$/', '', $forwardedHost) ?? $forwardedHost;
        }

        $forwardedPort = self::firstValue($_SERVER['HTTP_X_FORWARDED_PORT'] ?? '');
        if ($forwardedPort !== '' && ctype_digit($forwardedPort)) {
            $_SERVER['SERVER_PORT'] = $forwardedPort;
        }

        $forwardedProto = strtolower(self::firstValue($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto === 'https') {
            $_SERVER['HTTPS'] = 'on';
            $_SERVER['REQUEST_SCHEME'] = 'https';
        } elseif ($forwardedProto === 'http') {
            unset($_SERVER['HTTPS']);
            $_SERVER['REQUEST_SCHEME'] = 'http';
        }
    }

    private static function firstValue(string $headerValue): string
    {
        if ($headerValue === '') {
            return '';
        }
        $parts = explode(',', $headerValue);
        return trim($parts[0] ?? '');
    }

    private static function isTrusted(string $remoteAddr, array $trustedProxies): bool
    {
        foreach ($trustedProxies as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }
            if ($entry === '*') {
                return true;
            }
            if (strpos($entry, '/') !== false) {
                if (self::ipInCidr($remoteAddr, $entry)) {
                    return true;
                }
                continue;
            }
            if ($remoteAddr === $entry) {
                return true;
            }
        }
        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $prefixLength] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($subnet === null || $prefixLength === null || !ctype_digit($prefixLength)) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $prefix = (int) $prefixLength;
        $totalBits = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $totalBits) {
            return false;
        }

        $bytes = intdiv($prefix, 8);
        $bits = $prefix % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $bits)) & 0xFF;
        return ((ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask));
    }
}
