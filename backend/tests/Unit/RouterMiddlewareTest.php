<?php
namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use App\Exceptions\AuthorizationException;
use PHPUnit\Framework\TestCase;

class RouterMiddlewareTest extends TestCase {
    protected function tearDown(): void {
        $_SERVER = [];
    }

    public function test_middleware_rejection_throws_authorization_exception_with_custom_status(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/secure';

        $router = new Router();
        $router->get('/secure', fn()=>print('ok'), [fn()=>['status'=>403,'error'=>'Forbidden']]);

        try {
            $router->dispatch(new Request());
            self::fail('Expected AuthorizationException');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status());
            $this->assertSame('Forbidden', $exception->getMessage());
        }
    }

    public function test_router_populates_route_params_on_match(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/movies/interstellar';

        $router = new Router();
        $capturedSlug = null;

        $router->get('/api/movies/{slug}', function (Request $request) use (&$capturedSlug): void {
            $capturedSlug = $request->getRouteParam('slug');
        });

        $router->dispatch(new Request());

        $this->assertSame('interstellar', $capturedSlug);
    }
}
