<?php
namespace App\Services;

use App\Core\Database;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\PlaybackSessionRepository;
use App\Repositories\ShareLinkRepository;
use App\Repositories\UserContentAccessRepository;
use DateTimeImmutable;
use PDO;

class PlaybackService {
    public function __construct(
        private readonly AccessPolicyService $accessPolicyService = new AccessPolicyService(),
        private readonly CloudflareStreamService $cloudflareStreamService = new CloudflareStreamService(),
        private readonly PlaybackSessionRepository $playbackSessionRepository = new PlaybackSessionRepository(),
        private readonly ShareLinkRepository $shareLinkRepository = new ShareLinkRepository(),
        private readonly UserContentAccessRepository $userContentAccessRepository = new UserContentAccessRepository(),
        private readonly ?PDO $pdo = null,
    ) {}

    public function createSession(?array $user, string $playableType, int $playableId, ?string $shareToken): array {
        $normalizedType = strtolower(trim($playableType));
        if (!in_array($normalizedType, ['movie', 'episode'], true) || $playableId <= 0) {
            throw new ValidationException('Validation failed', [
                'playable_type' => ['The field must be one of: movie, episode.'],
                'playable_id' => ['The field must be a positive integer.'],
            ]);
        }

        $resolvedUser = $this->resolveActiveUser($user);
        $playable = $this->loadPlayableWithMedia($normalizedType, $playableId);
        if ($playable === null) {
            throw new NotFoundException('Playable not found');
        }

        if (
            empty($playable['media_asset_id'])
            || empty($playable['provider_uid'])
            || ($playable['media_status'] ?? '') !== 'ready'
        ) {
            throw new NotFoundException('Playback media is not ready');
        }

        $cleanShareToken = is_string($shareToken) && trim($shareToken) !== '' ? trim($shareToken) : null;
        $shareLinkId = $this->resolveShareLinkId($normalizedType, $playable, $cleanShareToken);
        $effectiveShareToken = $shareLinkId !== null ? $cleanShareToken : null;

        $contentForPolicy = [
            'status' => (string)($playable['status'] ?? 'draft'),
            'visibility' => (string)($playable['visibility'] ?? 'private'),
            'rights_status' => (string)($playable['rights_status'] ?? 'personal_only'),
            'public_streaming_enabled' => (bool)($playable['public_streaming_enabled'] ?? false),
            'is_listed_publicly' => (bool)($playable['is_listed_publicly'] ?? false),
            'public_starts_at' => $playable['public_starts_at'] ?? null,
            'public_ends_at' => $playable['public_ends_at'] ?? null,
            'public_from' => $playable['public_starts_at'] ?? null,
            'public_until' => $playable['public_ends_at'] ?? null,
            'assigned' => $this->isAssignedToUser($resolvedUser, $normalizedType, $playable),
        ];

        $this->accessPolicyService->assertCanWatch(
            $resolvedUser,
            $normalizedType,
            $playableId,
            $effectiveShareToken,
            $contentForPolicy,
        );

        $expiresAt = time() + ($resolvedUser !== null ? 3600 : 1800);
        $rawSessionToken = $this->base64UrlEncode(random_bytes(32));
        $sessionTokenHash = hash('sha256', $rawSessionToken);

        $sessionNumericId = $this->playbackSessionRepository->create([
            'session_token_hash' => $sessionTokenHash,
            'user_id' => $resolvedUser['id'] ?? null,
            'share_link_id' => $shareLinkId,
            'media_asset_id' => (int)$playable['media_asset_id'],
            'movie_id' => $playable['movie_id'] !== null ? (int)$playable['movie_id'] : null,
            'series_id' => $playable['series_id'] !== null ? (int)$playable['series_id'] : null,
            'episode_id' => $playable['episode_id'] !== null ? (int)$playable['episode_id'] : null,
            'expires_at' => gmdate('Y-m-d H:i:s', $expiresAt),
            'ip_address' => $this->clientIp(),
            'user_agent' => $this->clientUserAgent(),
        ]);

        $signedToken = $this->cloudflareStreamService->createSignedPlaybackToken(
            (string)$playable['provider_uid'],
            $expiresAt,
        );

        return [
            'playback_url' => $this->cloudflareStreamService->getHlsManifestUrl(
                (string)$playable['provider_uid'],
                $signedToken,
            ),
            'expires_at' => $expiresAt,
            'session_id' => $this->externalSessionId($sessionNumericId, $sessionTokenHash),
        ];
    }

    protected function resolveActiveUser(?array $user): ?array {
        if (empty($user['id'])) {
            return null;
        }

        $stmt = $this->pdo()->prepare('SELECT id, role, is_active FROM users WHERE id = ? LIMIT 1');
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

    protected function loadPlayableWithMedia(string $playableType, int $playableId): ?array {
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

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$playableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    protected function resolveShareLinkId(string $playableType, array $playable, ?string $shareToken): ?int {
        if ($shareToken === null) {
            return null;
        }

        $row = $this->shareLinkRepository->findByTokenHash(hash('sha256', $shareToken));
        if (!$row) {
            return null;
        }

        if ($playableType === 'movie') {
            if ((int)($row['movie_id'] ?? 0) !== (int)($playable['movie_id'] ?? 0)) {
                return null;
            }

            return (int)$row['id'];
        }

        $episodeMatch = (int)($row['episode_id'] ?? 0) === (int)($playable['episode_id'] ?? 0);
        $seriesMatch = (int)($row['series_id'] ?? 0) === (int)($playable['series_id'] ?? 0);
        return ($episodeMatch || $seriesMatch) ? (int)$row['id'] : null;
    }

    protected function isAssignedToUser(?array $user, string $playableType, array $playable): bool {
        if (empty($user['id'])) {
            return false;
        }

        if (($user['role'] ?? null) === 'admin') {
            return true;
        }

        if ($playableType === 'movie') {
            return $this->userContentAccessRepository->hasActiveAccess(
                (int)$user['id'],
                'movie',
                (int)$playable['movie_id'],
            );
        }

        $episodeId = (int)($playable['episode_id'] ?? 0);
        $seriesId = (int)($playable['series_id'] ?? 0);

        if ($episodeId > 0 && $this->userContentAccessRepository->hasActiveAccess((int)$user['id'], 'episode', $episodeId)) {
            return true;
        }

        return $seriesId > 0
            && $this->userContentAccessRepository->hasActiveAccess((int)$user['id'], 'series', $seriesId);
    }

    private function externalSessionId(int $sessionNumericId, string $sessionTokenHash): string {
        $prefix = substr($sessionTokenHash, 0, 12);
        return $sessionNumericId . '-' . $prefix;
    }

    private function clientIp(): ?string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    private function clientUserAgent(): ?string {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return is_string($userAgent) && $userAgent !== '' ? substr($userAgent, 0, 255) : null;
    }

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
