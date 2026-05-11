<?php
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuthService;

class AuthController {
    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly Validator $validator = new Validator(),
    ) {}

    public function login(Request $request): void {
        $in = $this->validator->validate($request->input(), [
            'email' => 'required|email',
            'password' => 'required|string|min:1',
        ]);

        $email = (string)$in['email'];
        $password = (string)$in['password'];
        $result = $this->auth->login($email, $password);
        if (!$result) {
            Response::json(['error' => ['code' => 'invalid_credentials', 'message' => 'Invalid credentials']], 401);
            return;
        }

        Response::json($result);
    }

    public function refresh(Request $request): void {
        $result = $this->auth->refresh((string)($request->input()['refresh_token'] ?? ''));
        if (!$result) {
            Response::json(['error' => ['code' => 'invalid_credentials', 'message' => 'Invalid refresh token']], 401);
            return;
        }

        Response::json($result);
    }

    public function logout(Request $request): void {
        $this->auth->logout((string)($request->input()['refresh_token'] ?? ''));
        Response::json(['ok'=>true]);
    }

    public function me(Request $request): void {
        $user = $request->attribute('auth_user') ?? $this->auth->validateAccessToken($request->bearerToken());
        if (!$user) { Response::json(['error'=>'Unauthorized'],401); return; }
        Response::json(['id'=>$user['id'],'role'=>$user['role']]);
    }
}
