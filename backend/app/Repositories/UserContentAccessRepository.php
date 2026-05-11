<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserContentAccessRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function findActiveForUser(int $userId, string $contentType, int $contentId): ?array {
        $column = match ($contentType) {
            'movie' => 'movie_id',
            'series' => 'series_id',
            'episode' => 'episode_id',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $stmt = $this->pdo()->prepare(
            "SELECT id, user_id, movie_id, series_id, episode_id, expires_at
             FROM user_content_access
             WHERE user_id = ?
               AND {$column} = ?
               AND (expires_at IS NULL OR expires_at > ?)
             LIMIT 1"
        );

        $stmt->execute([$userId, $contentId, gmdate('Y-m-d H:i:s')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function hasActiveAccess(int $userId, string $contentType, int $contentId): bool {
        return $this->findActiveForUser($userId, $contentType, $contentId) !== null;
    }
}
