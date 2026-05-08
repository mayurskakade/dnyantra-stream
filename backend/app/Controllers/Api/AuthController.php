<?php
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

class AuthController {
    public function __construct(private readonly AuthService $auth = new AuthService()) {}

    public function login(Request $request): void {
        $in = $request->input();
        $email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = (string)($in['password'] ?? '');
        if (!$email || $password === '') { Response::json(['error'=>'Invalid credentials payload'],422); return; }
        $result = $this->auth->login($email, $password);
        if (!$result) { Response::json(['error'=>'Invalid credentials'],401); return; }
        Response::json($result);
    }

    public function refresh(Request $request): void {
        $result = $this->auth->refresh((string)($request->input()['refresh_token'] ?? ''));
        if (!$result) { Response::json(['error'=>'Invalid refresh token'],401); return; }
        Response::json($result);
    }

    public function logout(Request $request): void {
        $this->auth->logout((string)($request->input()['refresh_token'] ?? ''));
        Response::json(['ok'=>true]);
    }

    public function me(Request $request): void {
        $user = $this->auth->validateAccessToken($request->bearerToken());
        if (!$user) { Response::json(['error'=>'Unauthorized'],401); return; }
        Response::json(['id'=>$user['id'],'role'=>$user['role']]);
    }
}
