<?php
namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;

class AccessPolicyService {
    private const RIGHTS_CLEARED = ['owned_by_me','licensed_public','public_domain','creative_commons'];

    public function canWatch(?array $user, string $playableType, int $playableId, ?string $shareToken = null, ?array $content = null): bool {
        if (!$content) {
            return false;
        }

        if (($content['status'] ?? 'draft') !== 'published') {
            return ($user['role'] ?? null) === 'admin';
        }

        $visibility = (string)($content['visibility'] ?? 'private');

        if ($visibility === 'private') {
            return ($user['role'] ?? null) === 'admin' || !empty($content['assigned']);
        }

        if ($visibility === 'authenticated') {
            return !empty($user) && ($user['is_active'] ?? true);
        }

        if ($visibility === 'unlisted') {
            return !empty($shareToken) || (!empty($user) && ($user['is_active'] ?? true));
        }

        if ($visibility === 'public') {
            return $this->isPublicAllowed($content);
        }

        return false;
    }

    public function isPublicAllowed(array $content): bool {
        if (!($content['public_streaming_enabled'] ?? false)) {
            return false;
        }

        $rightsStatus = (string)($content['rights_status'] ?? 'personal_only');
        if (!in_array($rightsStatus, self::RIGHTS_CLEARED, true)) {
            return false;
        }

        $publicFrom = $content['public_from'] ?? $content['public_starts_at'] ?? null;
        $publicUntil = $content['public_until'] ?? $content['public_ends_at'] ?? null;

        $now = new \DateTimeImmutable('now');
        if (!empty($publicFrom) && $now < new \DateTimeImmutable((string)$publicFrom)) {
            return false;
        }

        if (!empty($publicUntil) && $now > new \DateTimeImmutable((string)$publicUntil)) {
            return false;
        }

        return true;
    }

    public function filterListedForUser(array $rows, ?array $user): array {
        $visible = [];

        foreach ($rows as $row) {
            $content = [
                'status' => $row['status'] ?? 'published',
                'visibility' => $row['visibility'] ?? 'private',
                'rights_status' => $row['rights_status'] ?? 'personal_only',
                'public_streaming_enabled' => (bool)($row['public_streaming_enabled'] ?? false),
                'public_from' => $row['public_from'] ?? null,
                'public_until' => $row['public_until'] ?? null,
                'is_listed_publicly' => (bool)($row['is_listed_publicly'] ?? false),
                'assigned' => (bool)($row['assigned'] ?? false),
            ];

            if ($this->canWatch($user, 'content', (int)($row['id'] ?? 0), null, $content)) {
                $visible[] = $row;
            }
        }

        return $visible;
    }

    public function assertCanView(?array $user, string $type, int $id, array $content): void {
        if (!$this->canWatch($user, $type, $id, null, $content)) {
            throw new NotFoundException('Content not found');
        }
    }

    public function assertCanWatch(?array $user, string $type, int $id, ?string $shareToken, array $content): void {
        if (!$this->canWatch($user, $type, $id, $shareToken, $content)) {
            throw new AuthorizationException('Forbidden', 403, 'forbidden');
        }
    }
}
