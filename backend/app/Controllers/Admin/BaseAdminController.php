<?php
namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\AdminAuditLogRepository;
use App\Services\AdminAuditService;
use App\Support\View;
use PDO;

abstract class BaseAdminController {
    protected const PUBLIC_RIGHTS_ALLOWED = ['owned_by_me', 'licensed_public', 'public_domain', 'creative_commons'];

    public function __construct(
        protected readonly ?PDO $pdo = null,
        protected readonly Validator $validator = new Validator(),
        ?AdminAuditService $auditService = null,
    ) {
        $this->auditService = $auditService
            ?? new AdminAuditService(new AdminAuditLogRepository($this->pdo));
    }

    protected readonly AdminAuditService $auditService;

    protected function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    protected function renderPage(string $title, string $template, array $data = [], int $status = 200): void {
        $content = View::render($template, $data);
        Response::view(View::render('layouts/admin', [
            'title' => $title,
            'content' => $content,
        ]), $status);
    }

    protected function redirect(string $path): void {
        header('Location: ' . $path, true, 302);
    }

    protected function authAdminId(Request $request): int {
        return (int)(($request->attribute('auth_user') ?? [])['id'] ?? 0);
    }

    protected function findOrFail(string $table, int $id): array {
        $stmt = $this->pdo()->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException(ucfirst(rtrim($table, 's')) . ' not found');
        }

        return $row;
    }

    protected function slugify(string $value): string {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-') ?: 'item-' . time();
    }

    protected function boolFromInput(mixed $value, bool $default = false): bool {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }

    protected function normalizeNullableInt(mixed $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    protected function normalizeVisibilityFields(array $payload, array $existing = []): array {
        $visibility = (string)($payload['visibility'] ?? $existing['visibility'] ?? 'private');
        $rightsStatus = (string)($payload['rights_status'] ?? $existing['rights_status'] ?? 'personal_only');
        $publicStreamingEnabled = $this->boolFromInput(
            $payload['public_streaming_enabled'] ?? $existing['public_streaming_enabled'] ?? false,
            false,
        );

        if ($visibility === 'public') {
            if (!in_array($rightsStatus, self::PUBLIC_RIGHTS_ALLOWED, true)) {
                throw new ValidationException('Validation failed', [
                    'visibility' => ['Public visibility requires rights status that permits public distribution.'],
                    'rights_status' => ['Use one of: owned_by_me, licensed_public, public_domain, creative_commons.'],
                ]);
            }

            if ($publicStreamingEnabled !== true) {
                throw new ValidationException('Validation failed', [
                    'public_streaming_enabled' => ['Public visibility requires public_streaming_enabled=true.'],
                ]);
            }
        }

        return [
            'visibility' => $visibility,
            'rights_status' => $rightsStatus,
            'public_streaming_enabled' => $publicStreamingEnabled ? 1 : 0,
        ];
    }

    protected function flash(string $message, string $type = 'ok'): void {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    protected function audit(Request $request, string $action, string $entityType, ?int $entityId, ?array $before, ?array $after): void {
        $adminId = $this->authAdminId($request);
        if ($adminId <= 0) {
            return;
        }

        $this->auditService->record($adminId, $action, $entityType, $entityId, $before, $after, $request);
    }
}
