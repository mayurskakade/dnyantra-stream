<?php
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuthService;
use App\Services\RateLimiter;
use App\Services\RateLimiterFactory;

class AuthController {
    private RateLimiter $rateLimiter;

    public function __construct(
        private readonly AuthService $auth = new AuthService(),
        private readonly Validator $validator = new Validator(),
        ?RateLimiter $rateLimiter = null,
    ) {
        $this->rateLimiter = $rateLimiter ?? RateLimiterFactory::create();
    }

    public function login(Request $request): void {
        if ($this->isRateLimited('auth:login', 5, 60)) {
            return;
        }

        $in = $this->validator->validate($request->input(), [
            'email' => 'required|email',
            'password' => 'required|string|min:1',
        ]);

        $email = (string)$in['email'];
        $password = (string)$in['password'];
        $result = $this->auth->login($email, $password);
        if (!$result) {
            Response::json(['error' => 'Invalid credentials'], 401);
            return;
        }

        Response::json($result);
    }

    public function refresh(Request $request): void {
        if ($this->isRateLimited('auth:refresh', 30, 60)) {
            return;
        }

        $refreshToken = trim((string)($request->input()['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            Response::json([
                'error' => [
                    'code' => 'invalid_credentials',
                    'message' => 'Invalid refresh token',
                ],
            ], 401);
            return;
        }

        $result = $this->auth->refresh($refreshToken);
        if (!$result) {
            Response::json(['error' => 'Invalid refresh token'], 401);
            return;
        }

        Response::json($result);
    }

    public function logout(Request $request): void {
        $this->auth->logout((string)($request->input()['refresh_token'] ?? ''));
        Response::json(['ok'=>true]);
    }

    public function me(Request $request): void {
        $authUser = $request->attribute('auth_user') ?? $this->auth->validateAccessToken($request->bearerToken());
        if (!$authUser) {
            Response::json(['error' => 'Unauthorized'], 401);
            return;
        }

        $user = $this->auth->getUserById((int)($authUser['id'] ?? 0));
        if (!$user) {
            Response::json(['error' => 'Unauthorized'], 401);
            return;
        }

        Response::json(['user' => $user]);
    }

    private function isRateLimited(string $scope, int $limit, int $windowSeconds): bool {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $result = $this->rateLimiter->hit($scope . ':' . $ip, $limit, $windowSeconds);

        if ($result->allowed) {
            return false;
        }

        header('Retry-After: ' . $result->retryAfterSeconds);
        Response::json(['error' => 'Too many requests'], 429);
        return true;
    }
}
