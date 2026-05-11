<?php
namespace App\Services;

use App\Config\Config;
use App\Exceptions\ConfigurationException;

class RedisRateLimiter implements RateLimiter {
    public function __construct(private mixed $redis = null, private string $prefix = 'rl:') {
        $this->redis ??= $this->connect();
    }

    public function hit(string $key, int $limit, int $windowSeconds): RateLimitResult {
        $redisKey = $this->prefix . $key;

        $count = (int) $this->redis->incr($redisKey);
        if ($count === 1) {
            $this->redis->expire($redisKey, $windowSeconds);
        }

        $ttl = (int) $this->redis->ttl($redisKey);
        if ($ttl <= 0) {
            $this->redis->expire($redisKey, $windowSeconds);
            $ttl = $windowSeconds;
        }

        $allowed = $count <= $limit;
        $remaining = max(0, $limit - $count);
        $retryAfterSeconds = $allowed ? 0 : max(1, $ttl);

        return new RateLimitResult($allowed, $remaining, $retryAfterSeconds);
    }

    private function connect(): mixed {
        if (!class_exists('Redis')) {
            throw new ConfigurationException('Redis extension is required when REDIS_HOST is set');
        }

        $client = new \Redis();
        $host = (string) Config::require('REDIS_HOST');
        $port = (int) Config::get('REDIS_PORT', 6379);
        $timeout = (float) Config::get('REDIS_TIMEOUT', 1.5);

        if ($client->connect($host, $port, $timeout) === false) {
            throw new ConfigurationException('Unable to connect to Redis rate-limit backend');
        }

        return $client;
    }
}
