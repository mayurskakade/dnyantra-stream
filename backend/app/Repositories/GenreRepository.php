<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class GenreRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function listActive(): array {
        $stmt = $this->pdo()->prepare(
            'SELECT id, name, slug
             FROM genres
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
