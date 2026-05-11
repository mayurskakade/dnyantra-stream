<?php
namespace App\Services;

final class RateLimitResult {
    public function __construct(
        public bool $allowed,
        public int $remaining,
        public int $retryAfterSeconds
    ) {}
}
