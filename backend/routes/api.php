<?php
use App\Core\Response;
use App\Services\AuthService;

$auth = new AuthService();

$router->post('/api/auth/login', function($request) use ($auth) {
  $in = $request->input();
  $email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
  $password = (string)($in['password'] ?? '');
  if (!$email || $password === '') return Response::json(['error'=>'Invalid credentials payload'],422);
  $result = $auth->login($email, $password);
  if (!$result) return Response::json(['error'=>'Invalid credentials'],401);
  return Response::json($result);
});

$router->post('/api/auth/refresh', function($request) use ($auth) {
  $in = $request->input();
  $result = $auth->refresh((string)($in['refresh_token'] ?? ''));
  if (!$result) return Response::json(['error'=>'Invalid refresh token'],401);
  return Response::json($result);
});

$router->post('/api/auth/logout', function($request) use ($auth) {
  $in = $request->input();
  $auth->logout((string)($in['refresh_token'] ?? ''));
  return Response::json(['ok'=>true]);
});

$router->get('/api/me', function($request) use ($auth) {
  $user = $auth->validateAccessToken($request->bearerToken());
  if (!$user) return Response::json(['error'=>'Unauthorized'],401);
  return Response::json(['id'=>$user['id'],'role'=>$user['role']]);
});

$router->get('/api/home', fn()=>Response::json(['continue_watching'=>[],'featured'=>[]]));
$router->get('/api/categories', fn()=>Response::json(['data'=>[]]));
$router->get('/api/genres', fn()=>Response::json(['data'=>[]]));
$router->post('/api/playback-sessions', fn()=>Response::json(['error'=>'Playback policy + signed stream session not implemented yet'],501));
$router->post('/public/playback-sessions', fn()=>Response::json(['error'=>'Public playback policy + signed stream session not implemented yet'],501));
