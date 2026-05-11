<?php
namespace App\Services;

use App\Repositories\WatchProgressRepository;

class WatchProgressService {
    public function __construct(
        private readonly WatchProgressRepository $repository = new WatchProgressRepository(),
    ) {}

    public function compute(int $position, int $duration): array {
        $safePosition = max(0, $position);
        $safeDuration = max(0, $duration);
        $percent = $safeDuration > 0 ? round(($safePosition / $safeDuration) * 100, 2) : 0.0;

        return [
            'position_seconds' => $safePosition,
            'duration_seconds' => $safeDuration,
            'progress_percent' => $percent,
            'completed' => $safeDuration > 0 && ($safePosition / $safeDuration) >= 0.9,
        ];
    }

    public function save(int $userId, string $type, int $playableId, int $position, int $duration): array {
        $computed = $this->compute($position, $duration);

        return $this->repository->upsert(
            $userId,
            $type,
            $playableId,
            $computed['position_seconds'],
            $computed['duration_seconds'],
            (bool)$computed['completed'],
        );
    }

    public function markWatched(int $userId, string $type, int $playableId): array {
        $existing = $this->repository->findFor($userId, $type, $playableId);

        $duration = max(1, (int)($existing['duration_seconds'] ?? 0), (int)($existing['position_seconds'] ?? 0));

        return $this->repository->upsert(
            $userId,
            $type,
            $playableId,
            $duration,
            $duration,
            true,
        );
    }

    public function clear(int $userId, string $type, int $playableId): bool {
        $exists = $this->repository->findFor($userId, $type, $playableId) !== null;
        if (!$exists) {
            return false;
        }

        $this->repository->clear($userId, $type, $playableId);
        return true;
    }

    public function get(int $userId, string $type, int $playableId): ?array {
        return $this->repository->findFor($userId, $type, $playableId);
    }
}
