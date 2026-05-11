<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Repositories\AdminAuditLogRepository;
use PDO;

class AuditLogController extends BaseAdminController {
    public function __construct(
        ?PDO $pdo = null,
        private readonly AdminAuditLogRepository $auditLogRepository = new AdminAuditLogRepository(),
    ) {
        parent::__construct($pdo);
    }

    public function index(Request $request): void {
        $input = $request->input();
        $filters = [
            'admin_user_id' => $input['admin_user_id'] ?? null,
            'entity_type' => $input['entity_type'] ?? null,
            'action' => $input['action'] ?? null,
            'date_from' => $input['date_from'] ?? null,
            'date_to' => $input['date_to'] ?? null,
        ];

        $page = max(1, (int)($input['page'] ?? 1));
        $perPage = max(1, min(100, (int)($input['per_page'] ?? 20)));
        $result = $this->auditLogRepository->paginate($filters, $page, $perPage);

        $this->renderPage('Audit Logs', 'audit_logs/index', [
            'items' => $result['data'],
            'filters' => $filters,
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'total_pages' => (int)max(1, ceil(((int)$result['total']) / (int)$result['per_page'])),
        ]);
    }
}
