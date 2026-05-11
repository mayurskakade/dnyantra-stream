<?php
namespace Tests\Unit;

use App\Repositories\WatchProgressRepository;
use App\Services\WatchProgressService;
use PHPUnit\Framework\TestCase;

class WatchProgressServiceTest extends TestCase {
    public function test_progress_saves(): void {
        $service = new WatchProgressService(new InMemoryWatchProgressRepository());

        $saved = $service->save(1, 'movie', 9, 50, 100);

        $this->assertSame(50.0, (float)$saved['progress_percent']);
        $this->assertNotNull($service->get(1, 'movie', 9));
    }

    public function test_progress_marks_completed_over_ninety(): void {
        $service = new WatchProgressService(new InMemoryWatchProgressRepository());

        $result = $service->compute(91, 100);

        $this->assertTrue($result['completed']);
    }

    public function test_clear_progress_works(): void {
        $service = new WatchProgressService(new InMemoryWatchProgressRepository());

        $service->save(1, 'episode', 3, 10, 100);
        $this->assertTrue($service->clear(1, 'episode', 3));
        $this->assertNull($service->get(1, 'episode', 3));
    }
}

class InMemoryWatchProgressRepository extends WatchProgressRepository {
    private array $rows = [];

    public function __construct() {}

    public function upsert(int $userId, string $type, int $playableId, int $position, int $duration, bool $completed): array {
        $key = $this->key($userId, $type, $playableId);
        $progressPercent = $duration > 0 ? round(($position / $duration) * 100, 2) : 0.0;

        $this->rows[$key] = [
            'user_id' => $userId,
            'playable_type' => $type,
            'playable_id' => $playableId,
            'position_seconds' => $position,
            'duration_seconds' => $duration,
            'progress_percent' => $progressPercent,
            'completed' => $completed,
            'last_played_at' => gmdate(DATE_ATOM),
        ];

        return $this->rows[$key];
    }

    public function clear(int $userId, string $type, int $playableId): void {
        unset($this->rows[$this->key($userId, $type, $playableId)]);
    }

    public function findFor(int $userId, string $type, int $playableId): ?array {
        return $this->rows[$this->key($userId, $type, $playableId)] ?? null;
    }

    private function key(int $userId, string $type, int $playableId): string {
        return $userId . ':' . $type . ':' . $playableId;
    }
}
