<?php
namespace App\Middleware;

use App\Core\Request;
use App\Services\AuthService;

class AuthMiddleware {
    public function handle(Request $request): ?array {
        return (new AuthService())->validateAccessToken($request->bearerToken());
    }
}
