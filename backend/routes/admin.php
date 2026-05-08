<?php
use App\Core\Response;
use App\Middleware\AdminMiddleware;

$adminMiddleware = new AdminMiddleware();
$router->get('/admin/login', fn()=>Response::view('<h1>Admin Login</h1>'));
$router->get('/admin/dashboard', fn()=>Response::view('<h1>Dashboard</h1>'), [fn($request)=>$adminMiddleware->handle($request)]);
