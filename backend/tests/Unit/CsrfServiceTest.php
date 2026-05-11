<?php
namespace Tests\Unit;

use App\Services\CsrfService;
use PHPUnit\Framework\TestCase;

class CsrfServiceTest extends TestCase {
    protected function tearDown(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function test_token_is_stable_within_a_session(): void {
        $service = new CsrfService();

        $first = $service->token();
        $second = $service->token();

        $this->assertNotSame('', $first);
        $this->assertSame($first, $second);
    }

    public function test_validate_accepts_matching_token_and_rejects_mismatch(): void {
        $service = new CsrfService();

        $token = $service->token();

        $this->assertTrue($service->validate($token));
        $this->assertFalse($service->validate('invalid-token'));
        $this->assertFalse($service->validate(''));
    }
}
