<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class MediaAssetRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function createStreamUploadAsset(string $uid, ?string $filename = null): int {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO media_assets (provider, provider_uid, status, require_signed_playback, original_filename)
             VALUES (?, ?, ?, ?, ?)' 
        );
        $stmt->execute(['cloudflare_stream', $uid, 'uploading', 1, $filename]);
        return (int)$this->pdo()->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo()->prepare('SELECT * FROM media_assets WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status, ?int $durationSeconds, ?string $thumbnailUrl): void {
        $stmt = $this->pdo()->prepare(
            'UPDATE media_assets
             SET status = ?, duration_seconds = ?, thumbnail_url = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$status, $durationSeconds, $thumbnailUrl, gmdate('Y-m-d H:i:s'), $id]);
    }

    public function deleteById(int $id): void {
        $stmt = $this->pdo()->prepare('DELETE FROM media_assets WHERE id = ?');
        $stmt->execute([$id]);
    }
}
