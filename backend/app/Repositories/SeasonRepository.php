<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class SeasonRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function listBySeriesId(int $seriesId): array {
        $stmt = $this->pdo()->prepare(
            'SELECT
                se.id,
                se.season_number,
                se.title,
                COUNT(ep.id) AS episode_count
             FROM seasons se
             LEFT JOIN episodes ep ON ep.season_id = se.id AND ep.status = ?
             WHERE se.series_id = ?
             GROUP BY se.id, se.season_number, se.title
             ORDER BY se.season_number ASC'
        );

        $stmt->execute(['published', $seriesId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
