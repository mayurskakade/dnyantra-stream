<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class MovieRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function findForCatalog(array $filters = []): array {
        $params = [];
        $joins = [];
        $conditions = ['m.status = ?'];
        $params[] = 'published';

        if (!empty($filters['category_id'])) {
            $joins[] = 'INNER JOIN content_categories cc ON cc.movie_id = m.id';
            $conditions[] = 'cc.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['genre_id'])) {
            $joins[] = 'INNER JOIN content_genres cg ON cg.movie_id = m.id';
            $conditions[] = 'cg.genre_id = ?';
            $params[] = (int)$filters['genre_id'];
        }

        $sql = 'SELECT DISTINCT
                    m.id,
                    m.slug,
                    m.title,
                    m.synopsis,
                    m.duration_seconds,
                    m.poster_url,
                    m.banner_url,
                    m.release_year AS year,
                    m.visibility,
                    m.rights_status,
                    m.public_streaming_enabled,
                    m.public_from,
                    m.public_until,
                    m.is_listed_publicly,
                    m.media_asset_id,
                    ma.provider,
                    ma.provider_uid,
                    ma.status AS media_status
                FROM movies m
                LEFT JOIN media_assets ma ON ma.id = m.media_asset_id';

        if ($joins !== []) {
            $sql .= ' ' . implode(' ', array_unique($joins));
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY m.release_year DESC, m.id DESC';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findBySlug(string $slug): ?array {
        $stmt = $this->pdo()->prepare(
            'SELECT
                m.id,
                m.slug,
                m.title,
                m.synopsis,
                m.duration_seconds,
                m.poster_url,
                m.banner_url,
                m.release_year AS year,
                m.status,
                m.visibility,
                m.rights_status,
                m.public_streaming_enabled,
                m.public_from,
                m.public_until,
                m.is_listed_publicly,
                m.media_asset_id,
                ma.provider,
                ma.provider_uid,
                ma.status AS media_status
             FROM movies m
             LEFT JOIN media_assets ma ON ma.id = m.media_asset_id
             WHERE m.slug = ?
             LIMIT 1'
        );

        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listCategories(int $movieId): array {
        $stmt = $this->pdo()->prepare(
            'SELECT c.id, c.name, c.slug
             FROM content_categories cc
             INNER JOIN categories c ON c.id = cc.category_id
             WHERE cc.movie_id = ?
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        $stmt->execute([$movieId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listGenres(int $movieId): array {
        $stmt = $this->pdo()->prepare(
            'SELECT g.id, g.name, g.slug
             FROM content_genres cg
             INNER JOIN genres g ON g.id = cg.genre_id
             WHERE cg.movie_id = ?
             ORDER BY g.sort_order ASC, g.name ASC'
        );

        $stmt->execute([$movieId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
