<?php
namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase {
    public function test_attributes_are_request_scoped(): void {
        $req = new Request();
        $this->assertNull($req->attribute('auth_user'));
        $req->setAttribute('auth_user', ['id'=>1,'role'=>'admin']);
        $this->assertSame('admin', $req->attribute('auth_user')['role']);
    }

    public function test_get_route_param_returns_expected_value(): void {
        $req = new Request();
        $req->setAttribute('route.params', ['slug' => 'dune', 'id' => '42']);

        $this->assertSame('dune', $req->getRouteParam('slug'));
        $this->assertSame('42', $req->getRouteParam('id'));
        $this->assertNull($req->getRouteParam('missing'));
    }
}
