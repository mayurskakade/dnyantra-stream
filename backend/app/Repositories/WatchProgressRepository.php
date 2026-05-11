<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class WatchProgressRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function upsert(
        int $userId,
        string $type,
        int $playableId,
        int $position,
        int $duration,
        bool $completed,
    ): array {
        $progressPercent = $duration > 0 ? round(($position / $duration) * 100, 2) : 0.0;

        $stmt = $this->pdo()->prepare(
            'INSERT INTO watch_progress (
                user_id,
                playable_type,
                playable_id,
                duration_seconds,
                position_seconds,
                progress_percent,
                completed,
                completed_at,
                last_watched_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                position_seconds = VALUES(position_seconds),
                duration_seconds = VALUES(duration_seconds),
                progress_percent = VALUES(progress_percent),
                completed = VALUES(completed),
                completed_at = VALUES(completed_at),
                last_watched_at = NOW(),
                updated_at = NOW()'
        );

        $stmt->execute([
            $userId,
            $type,
            $playableId,
            $duration,
            $position,
            $progressPercent,
            $completed ? 1 : 0,
            $completed ? gmdate('Y-m-d H:i:s') : null,
        ]);

        return $this->findFor($userId, $type, $playableId) ?? [
            'user_id' => $userId,
            'playable_type' => $type,
            'playable_id' => $playableId,
            'position_seconds' => $position,
            'duration_seconds' => $duration,
            'progress_percent' => $progressPercent,
            'completed' => $completed,
            'last_played_at' => gmdate(DATE_ATOM),
        ];
    }

    public function clear(int $userId, string $type, int $playableId): void {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM watch_progress WHERE user_id = ? AND playable_type = ? AND playable_id = ? LIMIT 1'
        );

        $stmt->execute([$userId, $type, $playableId]);
    }

    public function findFor(int $userId, string $type, int $playableId): ?array {
        $stmt = $this->pdo()->prepare(
            'SELECT
                user_id,
                playable_type,
                playable_id,
                position_seconds,
                duration_seconds,
                progress_percent,
                completed,
                last_watched_at AS last_played_at,
                completed_at
             FROM watch_progress
             WHERE user_id = ?
               AND playable_type = ?
               AND playable_id = ?
             LIMIT 1'
        );

        $stmt->execute([$userId, $type, $playableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['user_id'] = (int)$row['user_id'];
        $row['playable_id'] = (int)$row['playable_id'];
        $row['position_seconds'] = (int)$row['position_seconds'];
        $row['duration_seconds'] = (int)$row['duration_seconds'];
        $row['progress_percent'] = (float)$row['progress_percent'];
        $row['completed'] = (bool)$row['completed'];

        return $row;
    }

    public function listContinueWatching(int $userId, int $limit = 20): array {
        $safeLimit = max(1, min(100, $limit));

        $stmt = $this->pdo()->prepare(
            'SELECT
                wp.playable_type,
                wp.playable_id,
                wp.position_seconds,
                wp.duration_seconds,
                wp.progress_percent,
                wp.completed,
                wp.last_watched_at AS last_played_at,
                m.title AS movie_title,
                m.poster_url AS movie_poster_url,
                e.title AS episode_title,
                s.poster_url AS series_poster_url
             FROM watch_progress wp
             LEFT JOIN movies m ON wp.playable_type = "movie" AND m.id = wp.playable_id
             LEFT JOIN episodes e ON wp.playable_type = "episode" AND e.id = wp.playable_id
             LEFT JOIN series s ON e.series_id = s.id
             WHERE wp.user_id = ?
               AND wp.completed = 0
             ORDER BY wp.last_watched_at DESC
             LIMIT ?'
        );

        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $safeLimit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
