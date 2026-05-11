<?php
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\PlaybackService;
use RuntimeException;

class PlaybackController {
    public function __construct(private readonly PlaybackService $playbackService = new PlaybackService()) {}

    public function create(Request $request): void {
        $this->createForRequest($request, $request->attribute('auth_user'));
    }

    public function createPublic(Request $request): void {
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
}
