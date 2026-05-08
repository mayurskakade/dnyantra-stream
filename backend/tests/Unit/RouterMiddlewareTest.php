<?php
namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

class RouterMiddlewareTest extends TestCase {
    public function test_middleware_can_short_circuit_with_custom_status(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/secure';

        $router = new Router();
        $router->get('/secure', fn()=>print('ok'), [fn()=>['status'=>403,'error'=>'Forbidden']]);

        ob_start();
        $router->dispatch(new Request());
        ob_end_clean();

        $this->assertSame(403, http_response_code());
    }
}
