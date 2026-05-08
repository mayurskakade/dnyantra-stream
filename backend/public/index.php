<?php
require __DIR__.'/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__)); $dotenv->safeLoad();
$request = new App\Core\Request(); $router = new App\Core\Router();
require __DIR__.'/../routes/api.php';
$router->dispatch($request);
