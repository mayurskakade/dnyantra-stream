<?php
namespace App\Middleware;

use App\Core\Request;
use App\Services\AuthService;

class AuthMiddleware {
    public function handle(Request $request): true|array {
        $user = (new AuthService())->validateAccessToken($request->bearerToken());
        if (!$user) return ['status'=>401,'error'=>'Unauthorized'];
        $request->setAttribute('auth_user', $user);
        return true;
    }
}
