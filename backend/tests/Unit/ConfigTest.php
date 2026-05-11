<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Config\Config;
use App\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private array $envBackup = [];

    protected function setUp(): void
    {
        $this->envBackup = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
    }

    public function test_boot_validate_throws_on_missing_required_key(): void
    {
        unset($_ENV['JWT_SECRET']);
        $_ENV['DB_HOST'] = '127.0.0.1';

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Missing required environment variable: JWT_SECRET');

        Config::bootValidate(['DB_HOST', 'JWT_SECRET']);
    }

    public function test_require_uses_fallback_key_for_cloudflare_api_token(): void
    {
        unset($_ENV['CLOUDFLARE_STREAM_API_TOKEN']);
        $_ENV['CLOUDFLARE_API_TOKEN'] = 'fallback-token';

        $this->assertSame('fallback-token', Config::require('CLOUDFLARE_STREAM_API_TOKEN'));
    }
}
