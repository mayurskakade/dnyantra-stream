<?php
require __DIR__.'/../vendor/autoload.php';
session_start();
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__)); $dotenv->safeLoad();
$request = new App\Core\Request(); $router = new App\Core\Router();
require __DIR__.'/../routes/admin.php';
$router->dispatch($request);
