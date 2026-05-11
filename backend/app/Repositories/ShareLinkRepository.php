<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class ShareLinkRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function findByTokenHash(string $tokenHash): ?array {
        $stmt = $this->pdo()->prepare(
            'SELECT id, token_hash, movie_id, series_id, episode_id, is_active, expires_at, max_uses, used_count, revoked_at
             FROM share_links
             WHERE token_hash = ?
               AND is_active = 1
               AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > ?)
               AND (max_uses IS NULL OR used_count < max_uses)
             LIMIT 1'
        );

        $stmt->execute([$tokenHash, gmdate('Y-m-d H:i:s')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
