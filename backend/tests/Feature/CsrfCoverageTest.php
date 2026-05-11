<?php
namespace Tests\Feature;

use App\Core\Request;
use App\Exceptions\AuthorizationException;
use App\Middleware\CsrfMiddleware;
use App\Services\CsrfService;
use App\Services\SessionService;
use PHPUnit\Framework\TestCase;

class CsrfCoverageTest extends TestCase {
    public function test_csrf_middleware_rejects_missing_or_wrong_token(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/admin/mock';
        $_POST = ['_csrf' => 'wrong-token'];

        $service = new CsrfService(new SessionService());
        $service->token();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(419);

        (new CsrfMiddleware($service))->handle(new Request());
    }

    public function test_admin_write_routes_require_csrf_when_present(): void {
        $adminRoutes = (string)file_get_contents(__DIR__ . '/../../routes/admin.php');
        preg_match_all('/\\$router->(post|put|patch|delete)\\(/i', $adminRoutes, $matches);
        $writeRouteCount = count($matches[0]);

        if ($writeRouteCount === 0) {
            $this->markTestSkipped('No admin write routes found yet (blocked on upstream admin CRUD integration).');
        }

        $this->assertStringContainsString('CsrfMiddleware', $adminRoutes, 'Admin write routes exist but CsrfMiddleware is not wired.');
    }
}
