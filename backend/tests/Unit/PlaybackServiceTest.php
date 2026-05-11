<?php
namespace Tests\Unit;

use App\Exceptions\AuthorizationException;
use App\Repositories\PlaybackSessionRepository;
use App\Repositories\ShareLinkRepository;
use App\Repositories\UserContentAccessRepository;
use App\Services\AccessPolicyService;
use App\Services\CloudflareStreamService;
use App\Services\PlaybackService;
use PHPUnit\Framework\TestCase;

class PlaybackServiceTest extends TestCase {
    public function test_private_content_without_assignment_throws_forbidden(): void {
        $service = new StubbedPlaybackService(
            playable: $this->moviePlayable('private'),
            resolvedUser: ['id' => 10, 'role' => 'viewer', 'is_active' => true],
            assigned: false,
        );

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(403);

        $service->createSession(['id' => 10, 'role' => 'viewer'], 'movie', 1, null);
    }

    public function test_public_content_outside_window_throws_forbidden(): void {
        $playable = $this->moviePlayable('public');
        $playable['public_starts_at'] = gmdate('Y-m-d H:i:s', time() + 3600);

        $service = new StubbedPlaybackService(
            playable: $playable,
            resolvedUser: null,
            assigned: false,
        );

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(403);

        $service->createSession(null, 'movie', 1, null);
    }

    public function test_happy_path_returns_contract_shape_and_hashes_token_before_persist(): void {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PlaybackServiceTest/1.0';

        $repository = new RecordingPlaybackSessionRepository();
        $service = new StubbedPlaybackService(
            playable: $this->moviePlayable('private'),
            resolvedUser: ['id' => 44, 'role' => 'viewer', 'is_active' => true],
            assigned: true,
            playbackSessionRepository: $repository,
        );

        $response = $service->createSession(['id' => 44, 'role' => 'viewer'], 'movie', 1, null);

        $this->assertSame(['playback_url', 'expires_at', 'session_id'], array_keys($response));
        $this->assertIsString($response['playback_url']);
        $this->assertIsInt($response['expires_at']);
        $this->assertIsString($response['session_id']);
        $this->assertArrayNotHasKey('provider_uid', $response);

        $this->assertGreaterThanOrEqual(time() + 3500, $response['expires_at']);
        $this->assertLessThanOrEqual(time() + 3700, $response['expires_at']);

        $payload = $repository->lastPayload;
        $this->assertIsArray($payload);
        $this->assertSame(64, strlen((string)$payload['session_token_hash']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string)$payload['session_token_hash']);
        $this->assertSame(44, (int)$payload['user_id']);
    }

    private function moviePlayable(string $visibility): array {
        return [
            'playable_id' => 1,
            'movie_id' => 1,
            'series_id' => null,
            'episode_id' => null,
            'media_asset_id' => 99,
            'status' => 'published',
            'visibility' => $visibility,
            'rights_status' => 'owned_by_me',
            'public_streaming_enabled' => true,
            'is_listed_publicly' => true,
            'public_starts_at' => gmdate('Y-m-d H:i:s', time() - 300),
            'public_ends_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'provider_uid' => 'video-abc',
            'media_status' => 'ready',
        ];
    }
}

class StubbedPlaybackService extends PlaybackService {
    public function __construct(
        private readonly array $playable,
        private readonly ?array $resolvedUser,
        private readonly bool $assigned,
        ?PlaybackSessionRepository $playbackSessionRepository = null,
    ) {
        parent::__construct(
            new AccessPolicyService(),
            new FakeCloudflareStreamService(),
            $playbackSessionRepository ?? new RecordingPlaybackSessionRepository(),
            new ShareLinkRepository(),
            new UserContentAccessRepository(),
            null,
        );
    }

    protected function resolveActiveUser(?array $user): ?array {
        return $this->resolvedUser;
    }

    protected function loadPlayableWithMedia(string $playableType, int $playableId): ?array {
        return $this->playable;
    }

    protected function resolveShareLinkId(string $playableType, array $playable, ?string $shareToken): ?int {
        return null;
    }

    protected function isAssignedToUser(?array $user, string $playableType, array $playable): bool {
        return $this->assigned;
    }
}

class FakeCloudflareStreamService extends CloudflareStreamService {
    public function __construct() {}

    public function createSignedPlaybackToken(string $videoUid, int $expiresAt, array $accessRules = []): string {
        return 'token-' . $videoUid . '-' . $expiresAt;
    }

    public function getHlsManifestUrl(string $videoUid, string $signedToken): string {
        return 'https://stream.example/' . $videoUid . '/manifest.m3u8?token=' . $signedToken;
    }
}

class RecordingPlaybackSessionRepository extends PlaybackSessionRepository {
    public ?array $lastPayload = null;

    public function __construct() {}

    public function create(array $payload): int {
        $this->lastPayload = $payload;
        return 123;
    }
}
