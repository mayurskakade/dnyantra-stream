<?php
namespace App\Middleware;

use App\Core\Request;
use App\Services\SessionService;

class AdminSessionMiddleware {
    public function __construct(private readonly SessionService $session = new SessionService()) {}

    public function handle(Request $request): true|array {
        $this->session->start();
        $user = $this->session->adminUser();
        if ($user === null) {
            return ['status' => 401, 'error' => 'Unauthorized'];
        }

        if (($user['role'] ?? null) !== 'admin') {
            return ['status' => 403, 'error' => 'Forbidden'];
        }

        $request->setAttribute('auth_user', $user);
        return true;
    }
}
