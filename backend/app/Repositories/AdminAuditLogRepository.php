<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

class AdminAuditLogRepository {
    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function insert(array $row): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO admin_audit_logs
                (admin_user_id, action, entity_type, entity_id, before_state, after_state, metadata, ip_address, user_agent, created_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
        );

        $stmt->execute([
            $row['admin_user_id'] ?? null,
            $row['action'] ?? '',
            $row['entity_type'] ?? '',
            $row['entity_id'] ?? null,
            $row['before_json'] ?? null,
            $row['after_json'] ?? null,
            $row['metadata_json'] ?? null,
            $row['ip'] ?? '',
            $row['user_agent'] ?? '',
            $row['created_at'] ?? gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 20): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if (!empty($filters['admin_user_id'])) {
            $where[] = 'admin_user_id = ?';
            $params[] = (int)$filters['admin_user_id'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $params[] = (string)$filters['entity_type'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = (string)$filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = (string)$filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = (string)$filters['date_to'] . ' 23:59:59';
        }

        $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));

        $countStmt = $this->pdo()->prepare('SELECT COUNT(*) FROM admin_audit_logs' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = 'SELECT id, admin_user_id, action, entity_type, entity_id, before_state, after_state, ip_address, user_agent, created_at
                FROM admin_audit_logs' . $whereSql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }
}
