<?php
declare(strict_types=1);

namespace Cardy;

class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }
        self::$data = require $path;
        self::$loaded = true;
    }

    /**
     * Get a config value using dot notation (e.g. 'db.host').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            throw new \RuntimeException('Config not loaded. Call Config::load() first.');
        }

        $segments = explode('.', $key);
        $value    = self::$data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
