<?php
namespace App\Config;

use App\Exceptions\ConfigurationException;

class Config {
    private const FALLBACK_KEYS = [
        'CLOUDFLARE_STREAM_API_TOKEN' => 'CLOUDFLARE_API_TOKEN',
    ];

    public static function get(string $key, mixed $default=null): mixed {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $fallbackKey = self::FALLBACK_KEYS[$key] ?? null;
        if ($fallbackKey !== null && array_key_exists($fallbackKey, $_ENV)) {
            return $_ENV[$fallbackKey];
        }

        return $default;
    }

    public static function require(string $key): string {
        $value = self::get($key);
        if ($value === null || trim((string) $value) === '') {
            throw new ConfigurationException("Missing required environment variable: {$key}");
        }

        return (string) $value;
    }

    public static function bootValidate(array $keys): void {
        foreach ($keys as $key) {
            self::require((string) $key);
        }
    }
}
