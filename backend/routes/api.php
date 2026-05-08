<?php
use App\Core\Response;
use App\Controllers\Api\AuthController;
use App\Middleware\AuthMiddleware;

$authController = new AuthController();
$authMiddleware = new AuthMiddleware();

$router->post('/api/auth/login', fn($request)=>$authController->login($request));
$router->post('/api/auth/refresh', fn($request)=>$authController->refresh($request));
$router->post('/api/auth/logout', fn($request)=>$authController->logout($request));
$router->get('/api/me', fn($request)=>$authController->me($request), [fn($request)=>$authMiddleware->handle($request)]);

$router->get('/api/home', fn()=>Response::json(['continue_watching'=>[],'featured'=>[]]));
$router->get('/api/categories', fn()=>Response::json(['data'=>[]]));
$router->get('/api/genres', fn()=>Response::json(['data'=>[]]));
$router->post('/api/playback-sessions', fn()=>Response::json(['error'=>'Playback policy + signed stream session not implemented yet'],501), [fn($request)=>$authMiddleware->handle($request)]);
$router->post('/public/playback-sessions', fn()=>Response::json(['error'=>'Public playback policy + signed stream session not implemented yet'],501));
