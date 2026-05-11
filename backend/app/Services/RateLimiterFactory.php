<?php
namespace App\Services;

use App\Config\Config;

final class RateLimiterFactory {
    public static function create(): RateLimiter {
        return Config::get('REDIS_HOST') ? new RedisRateLimiter() : new InMemoryRateLimiter();
    }
}
