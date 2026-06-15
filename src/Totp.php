<?php
declare(strict_types=1);

namespace Cardy;

class Totp
{
    private const BASE32   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DIGITS   = 6;
    private const PERIOD   = 30;
    private const WINDOW   = 1; // periods to allow either side of now (clock skew)

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $counter = (int) floor(($timestamp ?? time()) / self::PERIOD);
        $key     = self::base32Decode($secret);
        $msg     = pack('J', $counter);
        $hash    = hash_hmac('sha1', $msg, $key, true);
        $offset  = ord($hash[19]) & 0x0F;
        $code    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
             (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $now = time();
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::getCode($secret, $now + $i * self::PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function getOtpauthUrl(string $secret, string $username, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $username)
            . '?secret='  . rawurlencode($secret)
            . '&issuer='  . rawurlencode($issuer)
            . '&digits='  . self::DIGITS
            . '&period='  . self::PERIOD;
    }

    private static function base32Encode(string $bytes): string
    {
        $chars  = self::BASE32;
        $bits   = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($bits, 5) as $chunk) {
            $result .= $chars[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $result;
    }

    private static function base32Decode(string $encoded): string
    {
        $chars   = self::BASE32;
        $encoded = strtoupper(preg_replace('/[\s=]/', '', $encoded));
        $bits    = '';
        foreach (str_split($encoded) as $char) {
            $pos = strpos($chars, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $result .= chr(bindec($chunk));
            }
        }
        return $result;
    }
}
