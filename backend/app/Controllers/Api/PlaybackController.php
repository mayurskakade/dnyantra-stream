<?php
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Services\PlaybackService;
use App\Services\RateLimiter;
use App\Services\RateLimiterFactory;

class PlaybackController {
    private RateLimiter $rateLimiter;

    public function __construct(
        private readonly PlaybackService $playbackService = new PlaybackService(),
        private readonly Validator $validator = new Validator(),
        ?RateLimiter $rateLimiter = null,
    ) {
        $this->rateLimiter = $rateLimiter ?? RateLimiterFactory::create();
    }

    public function create(Request $request): void {
        $this->createForRequest($request, $request->attribute('auth_user'));
    }

    public function createPublic(Request $request): void {
        if ($this->isRateLimited('public:playback-sessions', 20, 300)) {
            return;
        }

        $this->createForRequest($request, null);
    }

    private function createForRequest(Request $request, ?array $user): void {
        $input = $this->validator->validate($request->input(), [
            'playable_type' => 'required|string|enum:movie,episode',
            'playable_id' => 'required|int|min:1',
        ]);

        $shareTokenRaw = $request->input()['share_token'] ?? null;
        if ($shareTokenRaw !== null && !is_string($shareTokenRaw)) {
            throw new ValidationException('Validation failed', [
                'share_token' => ['The field must be a string.'],
            ]);
        }

        $shareToken = is_string($shareTokenRaw) ? trim($shareTokenRaw) : null;
        if ($shareToken === '') {
            $shareToken = null;
        }

        $result = $this->playbackService->createSession(
            $user,
            strtolower((string)$input['playable_type']),
            (int)$input['playable_id'],
            $shareToken,
        );

        Response::json($result);
    }

    private function isRateLimited(string $scope, int $limit, int $windowSeconds): bool {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $result = $this->rateLimiter->hit($scope . ':' . $ip, $limit, $windowSeconds);

        if ($result->allowed) {
            return false;
        }

        header('Retry-After: ' . $result->retryAfterSeconds);
        throw new AuthorizationException('Rate limit exceeded', 429, 'rate_limited');
    }
}
