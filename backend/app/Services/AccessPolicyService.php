<?php
namespace App\Services;

class AccessPolicyService {
    private const RIGHTS_CLEARED = ['owned_by_me','licensed_public','public_domain','creative_commons'];
    public function canWatch(?array $user, string $playableType, int $playableId, ?string $shareToken=null, ?array $content=null): bool {
        if (!$content) return false;
        if (($content['status'] ?? 'draft') !== 'published') return ($user['role'] ?? null) === 'admin';
        $visibility = $content['visibility'] ?? 'private';
        if ($visibility === 'private') return ($user['role'] ?? null) === 'admin' || !empty($content['assigned']);
        if ($visibility === 'authenticated') return !empty($user) && ($user['is_active'] ?? false);
        if ($visibility === 'unlisted') return !empty($shareToken) || (!empty($user) && ($user['is_active'] ?? false));
        if ($visibility === 'public') return $this->isPublicAllowed($content);
        return false;
    }
    public function isPublicAllowed(array $content): bool {
        if (!($content['public_streaming_enabled'] ?? false)) return false;
        if (($content['rights_status'] ?? 'personal_only') === 'personal_only' || ($content['rights_status'] ?? '') === 'licensed_private') return false;
        if (!in_array($content['rights_status'] ?? '', self::RIGHTS_CLEARED, true)) return false;
        $now = new \DateTimeImmutable('now');
        if (!empty($content['public_from']) && $now < new \DateTimeImmutable($content['public_from'])) return false;
        if (!empty($content['public_until']) && $now > new \DateTimeImmutable($content['public_until'])) return false;
        return true;
    }
}
