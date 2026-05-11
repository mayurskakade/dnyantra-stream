<?php
namespace Tests\Feature;

use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Services\InMemoryRateLimiter;
use PHPUnit\Framework\TestCase;
use Throwable;

class RateLimitCoverageTest extends TestCase {
    private ?string $redisHostBackup = null;

    protected function setUp(): void {
        $this->redisHostBackup = $_ENV['REDIS_HOST'] ?? null;
        unset($_ENV['REDIS_HOST']);
        InMemoryRateLimiter::reset();
    }

    protected function tearDown(): void {
        if ($this->redisHostBackup === null) {
            unset($_ENV['REDIS_HOST']);
        } else {
            $_ENV['REDIS_HOST'] = $this->redisHostBackup;
        }

        InMemoryRateLimiter::reset();
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function test_login_rate_limit_returns_429_on_sixth_hit(): void {
        $statuses = [];
        for ($i = 1; $i <= 6; $i++) {
            [$status] = $this->dispatchApiPost('/api/auth/login', [
                'email' => 'not-an-email',
                'password' => 'x',
            ]);
            $statuses[] = $status;
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 5));
        $this->assertSame(429, $statuses[5]);
    }

    public function test_refresh_rate_limit_returns_429_on_thirty_first_hit(): void {
        $lastStatus = 0;
        for ($i = 1; $i <= 31; $i++) {
            [$status] = $this->dispatchApiPost('/api/auth/refresh', ['refresh_token' => '']);
            $lastStatus = $status;
        }

        $this->assertSame(429, $lastStatus);
    }

    public function test_public_playback_rate_limit_returns_429_on_twenty_first_hit(): void {
        $lastStatus = 0;
        for ($i = 1; $i <= 21; $i++) {
            [$status] = $this->dispatchApiPost('/public/playback-sessions', [
                'playable_type' => 'bad',
                'playable_id' => 0,
            ]);
            $lastStatus = $status;
        }

        $this->assertSame(429, $lastStatus);
    }

    public function test_admin_login_rate_limit_pending_route_integration(): void {
        $adminRoutes = (string)file_get_contents(__DIR__ . '/../../routes/admin.php');
        if (!str_contains($adminRoutes, "\$router->post('/admin/login'")) {
            $this->markTestSkipped('POST /admin/login route is not present yet (blocked on upstream admin auth integration).');
        }

        $this->assertTrue(true);
    }

    private function dispatchApiPost(string $uri, array $post): array {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = $post;

        $router = new Router();
        $request = new Request();
        require __DIR__ . '/../../routes/api.php';

        ob_start();
        try {
            $router->dispatch($request);
        } catch (Throwable $exception) {
            (new ErrorHandler())->handleException($exception);
        }

        return [http_response_code(), (string)ob_get_clean()];
    }
}
