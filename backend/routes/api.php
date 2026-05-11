<?php
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Controllers\Api\AuthController;
use App\Controllers\Api\PlaybackController;
use App\Middleware\AuthMiddleware;
use App\Services\RateLimiterFactory;

$authController = new AuthController();
$playbackController = new PlaybackController();
$authMiddleware = new AuthMiddleware();
$rateLimiter = RateLimiterFactory::create();

$clientIp = static function (): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
};

$loginRateLimit = static function ($request) use ($rateLimiter, $clientIp): bool {
    $email = strtolower(trim((string)($request->input()['email'] ?? '')));
    $result = $rateLimiter->hit('auth:login:' . $clientIp() . ':' . $email, 5, 60);
    if ($result->allowed) {
        return true;
    }

    header('Retry-After: ' . $result->retryAfterSeconds);
    throw new AuthorizationException('Rate limit exceeded', 429, 'rate_limited');
};

$refreshRateLimit = static function () use ($rateLimiter, $clientIp): bool {
    $result = $rateLimiter->hit('auth:refresh:' . $clientIp(), 30, 60);
    if ($result->allowed) {
        return true;
    }

    header('Retry-After: ' . $result->retryAfterSeconds);
    throw new AuthorizationException('Rate limit exceeded', 429, 'rate_limited');
};

$router->post('/api/auth/login', fn($request)=>$authController->login($request), [$loginRateLimit]);
$router->post('/api/auth/refresh', fn($request)=>$authController->refresh($request), [$refreshRateLimit]);
$router->post('/api/auth/logout', fn($request)=>$authController->logout($request));
$router->get('/api/me', fn($request)=>$authController->me($request), [fn($request)=>$authMiddleware->handle($request)]);

$router->get('/api/home', fn()=>Response::json(['continue_watching'=>[],'featured'=>[]]));
$router->get('/api/categories', fn()=>Response::json(['data'=>[]]));
$router->get('/api/genres', fn()=>Response::json(['data'=>[]]));
$router->post('/api/playback-sessions', fn($request)=>$playbackController->create($request), [fn($request)=>$authMiddleware->handle($request)]);
$router->post('/public/playback-sessions', fn($request)=>$playbackController->createPublic($request));
