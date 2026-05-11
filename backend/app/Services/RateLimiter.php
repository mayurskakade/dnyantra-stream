<?php
namespace App\Services;

interface RateLimiter {
    public function hit(string $key, int $limit, int $windowSeconds): RateLimitResult;
}
