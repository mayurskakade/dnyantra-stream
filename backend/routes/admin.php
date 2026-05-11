<?php
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Controllers\Admin\AdminAuthController;
use App\Middleware\AdminSessionMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\RateLimiterFactory;

$adminAuthController = new AdminAuthController();
$adminSessionMiddleware = new AdminSessionMiddleware();
$csrfMiddleware = new CsrfMiddleware();
$rateLimiter = RateLimiterFactory::create();

$clientIp = static function (): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
};

$adminLoginRateLimit = static function ($request) use ($rateLimiter, $clientIp): bool {
    $email = strtolower(trim((string)($request->input()['email'] ?? '')));
    $result = $rateLimiter->hit('admin:login:' . $clientIp() . ':' . $email, 5, 60);
    if ($result->allowed) {
        return true;
    }

    header('Retry-After: ' . $result->retryAfterSeconds);
    throw new AuthorizationException('Rate limit exceeded', 429, 'rate_limited');
};

// --- Agent 02: admin session auth + csrf ---
$router->get('/admin/login', fn($request)=>$adminAuthController->showLogin($request));
$router->post('/admin/login', fn($request)=>$adminAuthController->login($request), [
    fn($request)=>$csrfMiddleware->handle($request),
    $adminLoginRateLimit,
]);
$router->post('/admin/logout', fn($request)=>$adminAuthController->logout($request), [
    fn($request)=>$adminSessionMiddleware->handle($request),
    fn($request)=>$csrfMiddleware->handle($request),
]);
$router->get('/admin/dashboard', fn()=>Response::view('<h1>Dashboard</h1>'), [fn($request)=>$adminSessionMiddleware->handle($request)]);
