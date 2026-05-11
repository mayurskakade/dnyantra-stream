<?php
namespace Tests\Unit;

use App\Support\LogRedactor;
use PHPUnit\Framework\TestCase;

class LogRedactorTest extends TestCase {
    public function test_redacts_sensitive_keys_case_insensitively_and_recursively(): void {
        $redactor = new LogRedactor();

        $input = [
            'password' => 'secret-1',
            'Authorization' => 'Bearer abc',
            'safe' => 'ok',
            'nested' => [
                'token_hash' => 'hashed',
                'meta' => [
                    'csrf' => 'csrf-token',
                    'Cookie' => 'session=1',
                    'other' => 'value',
                ],
            ],
            'list' => [
                ['refresh_token' => 'r1'],
                ['safe' => 'still-ok'],
            ],
        ];

        $result = $redactor->redact($input);

        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('[REDACTED]', $result['Authorization']);
        $this->assertSame('ok', $result['safe']);
        $this->assertSame('[REDACTED]', $result['nested']['token_hash']);
        $this->assertSame('[REDACTED]', $result['nested']['meta']['csrf']);
        $this->assertSame('[REDACTED]', $result['nested']['meta']['Cookie']);
        $this->assertSame('value', $result['nested']['meta']['other']);
        $this->assertSame('[REDACTED]', $result['list'][0]['refresh_token']);
        $this->assertSame('still-ok', $result['list'][1]['safe']);
    }
}
