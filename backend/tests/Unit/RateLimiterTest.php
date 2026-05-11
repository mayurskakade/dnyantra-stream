<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InMemoryRateLimiter;
use App\Services\RateLimiterFactory;
use App\Services\RedisRateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        InMemoryRateLimiter::reset();
        unset($_ENV['REDIS_HOST']);
    }

    protected function tearDown(): void
    {
        InMemoryRateLimiter::reset();
        unset($_ENV['REDIS_HOST']);
    }

    public function test_in_memory_rate_limiter_allows_then_blocks_with_retry_after(): void
    {
        $limiter = new InMemoryRateLimiter();

        $first = $limiter->hit('auth:login:127.0.0.1', 2, 60);
        $second = $limiter->hit('auth:login:127.0.0.1', 2, 60);
        $third = $limiter->hit('auth:login:127.0.0.1', 2, 60);

        $this->assertTrue($first->allowed);
        $this->assertTrue($second->allowed);
        $this->assertFalse($third->allowed);
        $this->assertSame(0, $third->remaining);
        $this->assertGreaterThanOrEqual(1, $third->retryAfterSeconds);
    }

    public function test_redis_rate_limiter_increments_and_sets_expiry(): void
    {
        $redis = new FakeRedisClient();
        $limiter = new RedisRateLimiter($redis, 'rl:');

        $first = $limiter->hit('auth:refresh:127.0.0.1', 2, 120);
        $second = $limiter->hit('auth:refresh:127.0.0.1', 2, 120);
        $third = $limiter->hit('auth:refresh:127.0.0.1', 2, 120);

        $this->assertTrue($first->allowed);
        $this->assertTrue($second->allowed);
        $this->assertFalse($third->allowed);
        $this->assertSame(120, $redis->ttl('rl:auth:refresh:127.0.0.1'));
        $this->assertGreaterThanOrEqual(1, $third->retryAfterSeconds);
    }

    public function test_rate_limiter_factory_defaults_to_in_memory_backend_without_redis_host(): void
    {
        $limiter = RateLimiterFactory::create();
        $this->assertInstanceOf(InMemoryRateLimiter::class, $limiter);
    }
}

final class FakeRedisClient
{
    private array $counts = [];
    private array $ttls = [];

    public function incr(string $key): int
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
        return $this->counts[$key];
    }

    public function expire(string $key, int $seconds): bool
    {
        $this->ttls[$key] = $seconds;
        return true;
    }

    public function ttl(string $key): int
    {
        return $this->ttls[$key] ?? -1;
    }
}
