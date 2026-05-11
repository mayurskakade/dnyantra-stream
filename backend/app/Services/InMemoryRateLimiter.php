<?php
namespace App\Services;

class InMemoryRateLimiter implements RateLimiter {
    private static array $buckets = [];

    public function hit(string $key, int $limit, int $windowSeconds): RateLimitResult {
        $now = time();
        $bucket = self::$buckets[$key] ?? null;

        if (!$bucket || $bucket['expiresAt'] <= $now) {
            $bucket = ['count' => 0, 'expiresAt' => $now + $windowSeconds];
        }

        $bucket['count']++;
        self::$buckets[$key] = $bucket;

        $allowed = $bucket['count'] <= $limit;
        $remaining = max(0, $limit - $bucket['count']);
        $retryAfterSeconds = $allowed ? 0 : max(1, $bucket['expiresAt'] - $now);

        return new RateLimitResult($allowed, $remaining, $retryAfterSeconds);
    }

    public static function reset(): void {
        self::$buckets = [];
    }
}
