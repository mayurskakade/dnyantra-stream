<?php
namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Services\WatchProgressService;

class WatchProgressController {
    public function __construct(
        private readonly WatchProgressService $watchProgressService = new WatchProgressService(),
        private readonly Validator $validator = new Validator(),
    ) {}

    public function upsert(Request $request): void {
        $userId = $this->requireAuthUserId($request);
        $input = $this->validator->validate($request->input(), [
            'playable_type' => 'required|string|enum:movie,episode',
            'playable_id' => 'required|int|min:1',
            'position_seconds' => 'required|int|min:0',
            'duration_seconds' => 'required|int|min:0',
        ]);

        $position = (int)$input['position_seconds'];
        $duration = (int)$input['duration_seconds'];

        if ($duration > 0 && $position > $duration) {
            throw new ValidationException('Validation failed', [
                'position_seconds' => ['The field must be less than or equal to duration_seconds.'],
            ]);
        }

        $saved = $this->watchProgressService->save(
            $userId,
            strtolower((string)$input['playable_type']),
            (int)$input['playable_id'],
            $position,
            $duration,
        );

        Response::json([
            'completed' => (bool)$saved['completed'],
            'position_seconds' => (int)$saved['position_seconds'],
        ]);
    }

    public function markWatched(Request $request): void {
        $userId = $this->requireAuthUserId($request);
        $input = $this->validator->validate($request->input(), [
            'playable_type' => 'required|string|enum:movie,episode',
            'playable_id' => 'required|int|min:1',
        ]);

        $saved = $this->watchProgressService->markWatched(
            $userId,
            strtolower((string)$input['playable_type']),
            (int)$input['playable_id'],
        );

        Response::json([
            'completed' => (bool)$saved['completed'],
            'position_seconds' => (int)$saved['position_seconds'],
        ]);
    }

    public function clear(Request $request): void {
        $userId = $this->requireAuthUserId($request);
        $input = $this->validator->validate($request->input(), [
            'playable_type' => 'required|string|enum:movie,episode',
            'playable_id' => 'required|int|min:1',
        ]);

        $this->watchProgressService->clear(
            $userId,
            strtolower((string)$input['playable_type']),
            (int)$input['playable_id'],
        );

        Response::json(['ok' => true]);
    }

    private function requireAuthUserId(Request $request): int {
        $authUser = $request->attribute('auth_user');
        $userId = is_array($authUser) ? (int)($authUser['id'] ?? 0) : 0;

        if ($userId <= 0) {
            throw new AuthorizationException('Unauthorized', 401, 'authorization_failed');
        }

        return $userId;
    }
}
