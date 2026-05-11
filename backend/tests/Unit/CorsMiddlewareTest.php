<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Exceptions\AuthorizationException;
use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;

final class CorsMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
        header_remove();
        http_response_code(200);
    }

    public function test_allowed_preflight_returns_204_and_short_circuits(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/me';
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';

        $middleware = new CorsMiddleware('https://app.example.com,https://staging.example.com');
        $result = $middleware->handle(new Request());

        $this->assertFalse($result);
        $this->assertSame(204, http_response_code());

        if (function_exists('xdebug_get_headers')) {
            $headers = xdebug_get_headers();
            $this->assertContains('Access-Control-Allow-Origin: https://app.example.com', $headers);
            $this->assertContains('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS', $headers);
            $this->assertContains('Access-Control-Allow-Headers: Authorization, Content-Type, X-CSRF-Token', $headers);
        }
    }

    public function test_disallowed_preflight_is_blocked_with_403_json(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/public/playback-sessions';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example.com';

        $middleware = new CorsMiddleware('https://app.example.com');

        ob_start();
        $result = $middleware->handle(new Request());
        $body = (string) ob_get_clean();

        $this->assertFalse($result);
        $this->assertSame(403, http_response_code());
        $this->assertStringContainsString('cors_forbidden', $body);
    }

    public function test_disallowed_non_preflight_throws_authorization_exception(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/me';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example.com';

        $middleware = new CorsMiddleware('https://app.example.com');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Origin is not allowed.');

        $middleware->handle(new Request());
    }
}
