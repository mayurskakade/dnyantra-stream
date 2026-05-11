<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class EpisodeRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function listBySeasonId(int $seasonId): array {
        $stmt = $this->pdo()->prepare(
            'SELECT
                e.id,
                e.series_id,
                e.season_id,
                e.episode_number,
                e.title,
                e.synopsis,
                e.duration_seconds,
                e.visibility,
                e.status,
                s.poster_url AS series_poster_url,
                s.visibility AS series_visibility,
                s.rights_status AS series_rights_status,
                s.public_streaming_enabled AS series_public_streaming_enabled,
                s.public_from AS series_public_from,
                s.public_until AS series_public_until,
                s.is_listed_publicly AS series_is_listed_publicly
             FROM episodes e
             INNER JOIN series s ON s.id = e.series_id
             WHERE e.season_id = ?
               AND e.status = ?
             ORDER BY e.episode_number ASC, e.id ASC'
        );

        $stmt->execute([$seasonId, 'published']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
