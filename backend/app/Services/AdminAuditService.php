<?php
namespace App\Services;

use App\Core\Request;
use App\Repositories\AdminAuditLogRepository;

class AdminAuditService {
    public function __construct(private readonly AdminAuditLogRepository $repository = new AdminAuditLogRepository()) {}

    public function record(
        int $adminId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before,
        ?array $after,
        Request $request,
    ): void {
        $payload = [
            'admin_user_id' => $adminId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_SLASHES),
            'after_json' => $after === null ? null : json_encode($after, JSON_UNESCAPED_SLASHES),
            'metadata_json' => json_encode([
                'path' => $request->path(),
                'method' => $request->method(),
            ], JSON_UNESCAPED_SLASHES),
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        $this->repository->insert($payload);
    }

    public function log(
        int $adminId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before,
        ?array $after,
        Request $request,
    ): void {
        $this->record($adminId, $action, $entityType, $entityId, $before, $after, $request);
    }
}
