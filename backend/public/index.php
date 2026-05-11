<?php
require __DIR__.'/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__)); $dotenv->safeLoad();

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

$request = new App\Core\Request(); $router = new App\Core\Router();

$cors = new App\Middleware\CorsMiddleware((string) App\Config\Config::get('API_ALLOWED_ORIGINS', ''));
if ($cors->handle($request) === false) {
    return;
}

require __DIR__.'/../routes/api.php';
$router->dispatch($request);
