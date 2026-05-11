<?php
namespace App\Middleware;

use App\Exceptions\AuthorizationException;
use App\Core\Request;
use App\Services\CsrfService;

class CsrfMiddleware {
    public function __construct(private readonly CsrfService $csrf = new CsrfService()) {}

    public function handle(Request $request): true {
        $submitted = (string)($request->input()['_csrf'] ?? $request->header('X-CSRF-Token') ?? '');
        if (!$this->csrf->validate($submitted)) {
            throw new AuthorizationException('CSRF token is invalid', 419, 'csrf_invalid');
        }

        return true;
    }
}
