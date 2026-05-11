<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class PlaybackSessionRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function create(array $payload): int {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO playback_sessions (
                session_token_hash,
                user_id,
                share_link_id,
                media_asset_id,
                movie_id,
                series_id,
                episode_id,
                expires_at,
                ip_address,
                user_agent,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $stmt->execute([
            $payload['session_token_hash'],
            $payload['user_id'] ?? null,
            $payload['share_link_id'] ?? null,
            $payload['media_asset_id'],
            $payload['movie_id'] ?? null,
            $payload['series_id'] ?? null,
            $payload['episode_id'] ?? null,
            $payload['expires_at'],
            $payload['ip_address'] ?? null,
            $payload['user_agent'] ?? null,
        ]);

        return (int)$this->pdo()->lastInsertId();
    }
}
