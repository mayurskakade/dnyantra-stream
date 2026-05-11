<?php
require __DIR__.'/../vendor/autoload.php';
session_start();
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$errorHandler = new App\Core\ErrorHandler();
$errorHandler->register();

App\Config\Config::bootValidate([
    'DB_HOST',
    'JWT_SECRET',
    'CLOUDFLARE_ACCOUNT_ID',
    'CLOUDFLARE_STREAM_API_TOKEN',
    'CLOUDFLARE_STREAM_SIGNING_KEY_PEM',
    'R2_ACCOUNT_ID',
    'R2_ACCESS_KEY_ID',
    'R2_SECRET_ACCESS_KEY',
    'R2_BUCKET',
    'APP_URL',
    'API_ALLOWED_ORIGINS',
]);

$request = new App\Core\Request();
$router = new App\Core\Router();

// --- Agent 06: global admin middleware guard for non-login routes ---
$adminSessionMiddleware = new App\Middleware\AdminSessionMiddleware();
$csrfMiddleware = new App\Middleware\CsrfMiddleware();
$path = $request->path();

$isLoginRoute = $path === '/admin/login';
if (!$isLoginRoute) {
    $adminSessionMiddleware->handle($request);

    if ($request->method() === 'POST') {
        $csrfMiddleware->handle($request);
    }
}
require __DIR__.'/../routes/admin.php';
$router->dispatch($request);
