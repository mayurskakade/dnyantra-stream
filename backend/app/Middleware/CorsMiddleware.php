<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;

class CorsMiddleware {
    private array $allowedOrigins;

    public function __construct(string $allowedOriginsCsv) {
        $this->allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOriginsCsv))));
    }

    public function handle(Request $request): bool {
        if (!$this->isCorsRelevantPath($request->path())) {
            return true;
        }

        $origin = $request->header('Origin');
        if ($origin === null || $origin === '') {
            return true;
        }

        if (!in_array($origin, $this->allowedOrigins, true)) {
            if ($request->method() === 'OPTIONS') {
                Response::json(['error' => ['code' => 'cors_forbidden', 'message' => 'Origin is not allowed.']], 403);
                return false;
            }

            throw new AuthorizationException('Origin is not allowed.', 403, 'cors_forbidden');
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-CSRF-Token');
        header('Access-Control-Max-Age: 600');

        if ($request->method() === 'OPTIONS') {
            http_response_code(204);
            return false;
        }

        return true;
    }

    private function isCorsRelevantPath(string $path): bool {
        return $path === '/api'
            || $path === '/public'
            || str_starts_with($path, '/api/')
            || str_starts_with($path, '/public/');
    }
}
