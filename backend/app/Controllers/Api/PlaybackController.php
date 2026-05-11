<?php
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\PlaybackService;
use App\Services\RateLimiter;
use App\Services\RateLimiterFactory;
use RuntimeException;

class PlaybackController {
    private RateLimiter $rateLimiter;

    public function __construct(
        private readonly PlaybackService $playbackService = new PlaybackService(),
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
        $input = $request->input();

        $playableType = strtolower(trim((string)($input['playable_type'] ?? '')));
        $playableIdRaw = $input['playable_id'] ?? null;
        $shareTokenRaw = $input['share_token'] ?? null;

        if (!in_array($playableType, ['movie', 'episode'], true)) {
            Response::json(['error' => 'Invalid payload'], 422);
            return;
        }

        if (filter_var($playableIdRaw, FILTER_VALIDATE_INT) === false || (int)$playableIdRaw <= 0) {
            Response::json(['error' => 'Invalid payload'], 422);
            return;
        }

        if ($shareTokenRaw !== null && !is_string($shareTokenRaw)) {
            Response::json(['error' => 'Invalid payload'], 422);
            return;
        }

        $shareToken = $shareTokenRaw !== null ? trim($shareTokenRaw) : null;
        if ($shareToken === '') {
            $shareToken = null;
        }

        try {
            $result = $this->playbackService->createSession($user, $playableType, (int)$playableIdRaw, $shareToken);
            Response::json($result);
            return;
        } catch (RuntimeException $e) {
            $status = $e->getCode();
            if ($status === 422) {
                Response::json(['error' => 'Invalid payload'], 422);
                return;
            }
            if ($status === 403) {
                Response::json(['error' => 'Forbidden'], 403);
                return;
            }
            if ($status === 404) {
                Response::json(['error' => 'Not found'], 404);
                return;
            }

            Response::json(['error' => 'Unable to create playback session'], 500);
        }
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
