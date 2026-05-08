<?php
use App\Core\Response;
$router->get('/admin/login', fn()=>Response::view('<h1>Admin Login</h1>'));
$router->get('/admin/dashboard', fn()=>Response::view('<h1>Dashboard</h1>'));
