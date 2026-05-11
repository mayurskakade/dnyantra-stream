<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class SeriesRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function findForCatalog(array $filters = []): array {
        $params = [];
        $joins = [];
        $conditions = ['s.status = ?'];
        $params[] = 'published';

        if (!empty($filters['category_id'])) {
            $joins[] = 'INNER JOIN content_categories cc ON cc.series_id = s.id';
            $conditions[] = 'cc.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['genre_id'])) {
            $joins[] = 'INNER JOIN content_genres cg ON cg.series_id = s.id';
            $conditions[] = 'cg.genre_id = ?';
            $params[] = (int)$filters['genre_id'];
        }

        $sql = 'SELECT DISTINCT
                    s.id,
                    s.slug,
                    s.title,
                    s.synopsis,
                    s.poster_url,
                    s.banner_url,
                    s.release_year AS year,
                    s.visibility,
                    s.rights_status,
                    s.public_streaming_enabled,
                    s.public_from,
                    s.public_until,
                    s.is_listed_publicly
                FROM series s';

        if ($joins !== []) {
            $sql .= ' ' . implode(' ', array_unique($joins));
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY s.release_year DESC, s.id DESC';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findBySlug(string $slug): ?array {
        $stmt = $this->pdo()->prepare(
            'SELECT
                id,
                slug,
                title,
                synopsis,
                poster_url,
                banner_url,
                release_year AS year,
                status,
                visibility,
                rights_status,
                public_streaming_enabled,
                public_from,
                public_until,
                is_listed_publicly
             FROM series
             WHERE slug = ?
             LIMIT 1'
        );

        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
