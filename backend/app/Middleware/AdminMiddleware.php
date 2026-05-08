<?php
namespace App\Middleware;

use App\Core\Request;

class AdminMiddleware {
    public function handle(Request $request): bool {
        $user = (new AuthMiddleware())->handle($request);
        return !empty($user) && (($user['role'] ?? null) === 'admin');
    }
}
