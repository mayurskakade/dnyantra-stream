<?php
namespace App\Services;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class PlaybackService {
    public function __construct(
        private readonly AccessPolicyService $accessPolicyService = new AccessPolicyService(),
        private readonly CloudflareStreamService $cloudflareStreamService = new CloudflareStreamService(),
    ) {}

    public function createSession(?array $user, string $playableType, int $playableId, ?string $shareToken): array {
        $normalizedType = strtolower(trim($playableType));
        if (!in_array($normalizedType, ['movie', 'episode'], true) || $playableId <= 0) {
            throw new RuntimeException('Invalid playable payload', 422);
        }

        $pdo = Database::pdo();
        $resolvedUser = $this->resolveActiveUser($pdo, $user);
        $playable = $this->loadPlayableWithMedia($pdo, $normalizedType, $playableId);
        if (!$playable) {
            throw new RuntimeException('Content not found', 404);
        }

        if (empty($playable['media_asset_id']) || empty($playable['provider_uid']) || ($playable['media_status'] ?? '') !== 'ready') {
            throw new RuntimeException('Media not found', 404);
        }

        $shareToken = is_string($shareToken) && trim($shareToken) !== '' ? trim($shareToken) : null;
        $shareLinkId = $this->resolveShareLinkId($pdo, $normalizedType, $playable, $shareToken);
        $effectiveShareToken = $shareLinkId ? $shareToken : null;

        $contentForPolicy = [
            'status' => $playable['status'],
            'visibility' => $playable['visibility'],
            'rights_status' => $playable['rights_status'],
            'public_streaming_enabled' => (bool)$playable['public_streaming_enabled'],
            'is_listed_publicly' => (bool)$playable['is_listed_publicly'],
            'requires_authentication' => ($playable['visibility'] ?? 'private') === 'authenticated',
            'share_enabled' => ($playable['visibility'] ?? 'private') === 'unlisted',
            'public_starts_at' => $playable['public_starts_at'],
            'public_ends_at' => $playable['public_ends_at'],
            // AccessPolicyService currently checks these field names.
            'public_from' => $playable['public_starts_at'],
            'public_until' => $playable['public_ends_at'],
            'assigned' => $this->isAssignedToUser($pdo, $resolvedUser, $normalizedType, $playable),
        ];

        $canWatch = $this->accessPolicyService->canWatch(
            $resolvedUser,
            $normalizedType,
            $playableId,
            $effectiveShareToken,
            $contentForPolicy
        );

        if (!$canWatch) {
            throw new RuntimeException('Access denied', 403);
        }

        $expiresAt = (new DateTimeImmutable('now'))->add(new DateInterval($resolvedUser ? 'PT60M' : 'PT30M'));
        $sessionToken = $this->base64UrlEncode(random_bytes(32));
        $sessionTokenHash = hash('sha256', $sessionToken);

        $insert = $pdo->prepare(
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

        $insert->execute([
            $sessionTokenHash,
            $resolvedUser['id'] ?? null,
            $shareLinkId,
            (int)$playable['media_asset_id'],
            $playable['movie_id'] ? (int)$playable['movie_id'] : null,
            $playable['series_id'] ? (int)$playable['series_id'] : null,
            $playable['episode_id'] ? (int)$playable['episode_id'] : null,
            $expiresAt->format('Y-m-d H:i:s'),
            $this->clientIp(),
            $this->clientUserAgent(),
        ]);

        $sessionId = (int)$pdo->lastInsertId();
        $signedToken = $this->cloudflareStreamService->createSignedPlaybackToken((string)$playable['provider_uid'], $expiresAt);

        return [
            'playback_url' => $this->cloudflareStreamService->getHlsManifestUrl((string)$playable['provider_uid'], $signedToken),
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'session_id' => $sessionId,
        ];
    }

    private function loadPlayableWithMedia(PDO $pdo, string $playableType, int $playableId): ?array {
        $sql = $playableType === 'movie'
            ? 'SELECT
                    m.id AS playable_id,
                    m.id AS movie_id,
                    NULL AS series_id,
                    NULL AS episode_id,
                    m.media_asset_id,
                    m.status,
                    m.visibility,
                    m.rights_status,
                    m.public_streaming_enabled,
                    m.is_listed_publicly,
                    m.public_from AS public_starts_at,
                    m.public_until AS public_ends_at,
                    ma.provider_uid,
                    ma.status AS media_status
               FROM movies m
               LEFT JOIN media_assets ma ON ma.id = m.media_asset_id
               WHERE m.id = ?
               LIMIT 1'
            : 'SELECT
                    e.id AS playable_id,
                    NULL AS movie_id,
                    e.series_id,
                    e.id AS episode_id,
                    e.media_asset_id,
                    e.status,
                    COALESCE(e.visibility, s.visibility) AS visibility,
                    s.rights_status,
                    s.public_streaming_enabled,
                    s.is_listed_publicly,
                    s.public_from AS public_starts_at,
                    s.public_until AS public_ends_at,
                    ma.provider_uid,
                    ma.status AS media_status
               FROM episodes e
               INNER JOIN series s ON s.id = e.series_id
               LEFT JOIN media_assets ma ON ma.id = e.media_asset_id
               WHERE e.id = ?
               LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$playableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function resolveActiveUser(PDO $pdo, ?array $user): ?array {
        if (empty($user['id'])) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !(bool)$row['is_active']) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'role' => (string)$row['role'],
            'is_active' => (bool)$row['is_active'],
        ];
    }

    private function resolveShareLinkId(PDO $pdo, string $playableType, array $playable, ?string $shareToken): ?int {
        if ($shareToken === null) {
            return null;
        }

        $hashed = hash('sha256', $shareToken);

        if ($playableType === 'movie') {
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM share_links
                 WHERE token_hash = ?
                   AND movie_id = ?
                   AND is_active = 1
                   AND revoked_at IS NULL
                   AND (expires_at IS NULL OR expires_at > NOW())
                   AND (max_uses IS NULL OR used_count < max_uses)
                 LIMIT 1'
            );
            $stmt->execute([$hashed, (int)$playable['movie_id']]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM share_links
                 WHERE token_hash = ?
                   AND (episode_id = ? OR series_id = ?)
                   AND is_active = 1
                   AND revoked_at IS NULL
                   AND (expires_at IS NULL OR expires_at > NOW())
                   AND (max_uses IS NULL OR used_count < max_uses)
                 LIMIT 1'
            );
            $stmt->execute([$hashed, (int)$playable['episode_id'], (int)$playable['series_id']]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id'] : null;
    }

    private function isAssignedToUser(PDO $pdo, ?array $user, string $playableType, array $playable): bool {
        if (empty($user['id'])) {
            return false;
        }

        if (($user['role'] ?? null) === 'admin') {
            return true;
        }

        if ($playableType === 'movie') {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM user_content_access
                 WHERE user_id = ?
                   AND movie_id = ?
                   AND (expires_at IS NULL OR expires_at > NOW())
                 LIMIT 1'
            );
            $stmt->execute([(int)$user['id'], (int)$playable['movie_id']]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM user_content_access
                 WHERE user_id = ?
                   AND (episode_id = ? OR series_id = ?)
                   AND (expires_at IS NULL OR expires_at > NOW())
                 LIMIT 1'
            );
            $stmt->execute([(int)$user['id'], (int)$playable['episode_id'], (int)$playable['series_id']]);
        }

        return (bool)$stmt->fetchColumn();
    }

    private function clientIp(): ?string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    private function clientUserAgent(): ?string {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return is_string($userAgent) && $userAgent !== '' ? substr($userAgent, 0, 255) : null;
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
