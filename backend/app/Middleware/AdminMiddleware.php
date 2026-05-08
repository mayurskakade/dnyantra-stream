<?php
namespace App\Middleware;

use App\Core\Request;

class AdminMiddleware {
    public function handle(Request $request): true|array {
        $result = (new AuthMiddleware())->handle($request);
        if ($result !== true) return $result;
        $user = $GLOBALS['auth_user'] ?? null;
        if (($user['role'] ?? null) !== 'admin') return ['status'=>403,'error'=>'Forbidden'];
        return true;
    }
}
