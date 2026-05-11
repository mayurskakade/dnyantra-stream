<?php
namespace Tests\Unit;

use App\Repositories\WatchProgressRepository;
use App\Services\WatchProgressService;
use PHPUnit\Framework\TestCase;

class WatchProgressServiceDbTest extends TestCase {
    public function test_upsert_is_idempotent_for_same_user_and_playable(): void {
        $repo = new CountingWatchProgressRepository();
        $service = new WatchProgressService($repo);

        for ($i = 1; $i <= 100; $i++) {
            $service->save(10, 'movie', 200, $i, 1000);
        }

        $current = $service->get(10, 'movie', 200);

        $this->assertSame(1, $repo->rowCount());
        $this->assertNotNull($current);
        $this->assertSame(100, (int)$current['position_seconds']);
        $this->assertSame(1000, (int)$current['duration_seconds']);
    }

    public function test_completion_threshold_marks_completed_at_ninety_percent(): void {
        $service = new WatchProgressService(new CountingWatchProgressRepository());

        $saved = $service->save(4, 'episode', 12, 90, 100);

        $this->assertTrue((bool)$saved['completed']);
        $this->assertSame(90.0, (float)$saved['progress_percent']);
    }

    public function test_clear_removes_row(): void {
        $service = new WatchProgressService(new CountingWatchProgressRepository());

        $service->save(9, 'episode', 88, 10, 100);

        $this->assertTrue($service->clear(9, 'episode', 88));
        $this->assertNull($service->get(9, 'episode', 88));
    }
}

class CountingWatchProgressRepository extends WatchProgressRepository {
    private array $rows = [];

    public function __construct() {}

    public function upsert(int $userId, string $type, int $playableId, int $position, int $duration, bool $completed): array {
        $progressPercent = $duration > 0 ? round(($position / $duration) * 100, 2) : 0.0;
        $this->rows[$this->key($userId, $type, $playableId)] = [
            'user_id' => $userId,
            'playable_type' => $type,
            'playable_id' => $playableId,
            'position_seconds' => $position,
            'duration_seconds' => $duration,
            'progress_percent' => $progressPercent,
            'completed' => $completed,
            'last_played_at' => gmdate(DATE_ATOM),
        ];

        return $this->rows[$this->key($userId, $type, $playableId)];
    }

    public function clear(int $userId, string $type, int $playableId): void {
        unset($this->rows[$this->key($userId, $type, $playableId)]);
    }

    public function findFor(int $userId, string $type, int $playableId): ?array {
        return $this->rows[$this->key($userId, $type, $playableId)] ?? null;
    }

    public function rowCount(): int {
        return count($this->rows);
    }

    private function key(int $userId, string $type, int $playableId): string {
        return $userId . ':' . $type . ':' . $playableId;
    }
}
