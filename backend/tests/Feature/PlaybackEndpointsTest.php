<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDO;

final class PlaybackEndpointsTest extends FeatureTestCase
{
    public function testPlaybackSessionEndpointReturns422ForInvalidPayload(): void
    {
        $token = $this->makeAccessToken(1, 'viewer');

        $response = $this->dispatch('POST', '/api/playback-sessions', [
            'playable_type' => 'bad-type',
            'playable_id' => 0,
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $this->assertSame(422, $response['status']);
        $this->assertSame('validation_failed', $response['json']['error']['code'] ?? null);
    }

    public function testPrivateContentWithoutAssignmentReturns403WhenDatabaseIsAvailable(): void
    {
        $pdo = $this->requirePlaybackDatabase();
        $this->seedCloudflareSigningEnv();

        $userId = $this->insertUser($pdo);
        $movieId = $this->insertMovieWithMedia($pdo, 'private');

        $response = $this->dispatch('POST', '/api/playback-sessions', [
            'playable_type' => 'movie',
            'playable_id' => $movieId,
        ], [
            'Authorization' => 'Bearer ' . $this->makeAccessToken($userId, 'viewer'),
        ]);

        $this->assertSame(403, $response['status']);
        $this->assertSame('forbidden', $response['json']['error']['code'] ?? null);
    }

    public function testAssignedPrivateContentReturnsSignedPlaybackContractWhenDatabaseIsAvailable(): void
    {
        $pdo = $this->requirePlaybackDatabase();
        $this->seedCloudflareSigningEnv();

        $userId = $this->insertUser($pdo);
        $movieId = $this->insertMovieWithMedia($pdo, 'private');

        $grant = $pdo->prepare(
            'INSERT INTO user_content_access (user_id, movie_id, access_type, expires_at) VALUES (?, ?, ?, ?)' 
        );
        $grant->execute([$userId, $movieId, 'granted', gmdate('Y-m-d H:i:s', time() + 3600)]);

        $response = $this->dispatch('POST', '/api/playback-sessions', [
            'playable_type' => 'movie',
            'playable_id' => $movieId,
        ], [
            'Authorization' => 'Bearer ' . $this->makeAccessToken($userId, 'viewer'),
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertIsString($response['json']['playback_url'] ?? null);
        $this->assertIsInt($response['json']['expires_at'] ?? null);
        $this->assertIsString($response['json']['session_id'] ?? null);
        $this->assertArrayNotHasKey('provider_uid', $response['json'] ?? []);

        $sessionRow = $pdo->query('SELECT session_token_hash FROM playback_sessions ORDER BY id DESC LIMIT 1')?->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($sessionRow);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string)($sessionRow['session_token_hash'] ?? ''));
    }

    public function testPublicPlaybackRouteRateLimitsAfterTwentyRequests(): void
    {
        $lastStatus = 0;

        for ($i = 1; $i <= 21; $i++) {
            $response = $this->dispatch('POST', '/public/playback-sessions', [
                'playable_type' => 'bad',
                'playable_id' => 0,
            ]);
            $lastStatus = $response['status'];
        }

        $this->assertSame(429, $lastStatus);
    }

    private function requirePlaybackDatabase(): PDO
    {
        if (!$this->hasDatabaseConnection()) {
            $this->markTestSkipped('Database unavailable for playback feature tests.');
        }

        $pdo = $this->pdo();
        if (!$pdo instanceof PDO) {
            $this->markTestSkipped('Unable to initialize PDO for playback feature tests.');
        }

        $required = ['users', 'movies', 'media_assets', 'playback_sessions', 'user_content_access'];
        foreach ($required as $table) {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            if (!$stmt->fetchColumn()) {
                $this->markTestSkipped('Missing table for playback test: ' . $table);
            }
        }

        return $pdo;
    }

    private function insertUser(PDO $pdo): int
    {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([
            'Playback User',
            'playback+' . uniqid('', true) . '@example.com',
            password_hash('secret', PASSWORD_DEFAULT),
            'viewer',
        ]);

        return (int)$pdo->lastInsertId();
    }

    private function insertMovieWithMedia(PDO $pdo, string $visibility): int
    {
        $asset = $pdo->prepare(
            'INSERT INTO media_assets (provider, provider_uid, status, require_signed_playback, original_filename) VALUES (?, ?, ?, ?, ?)'
        );
        $asset->execute(['cloudflare_stream', 'uid-' . uniqid('', true), 'ready', 1, 'movie.mp4']);
        $assetId = (int)$pdo->lastInsertId();

        $movie = $pdo->prepare(
            'INSERT INTO movies (
                title, slug, synopsis, duration_seconds, media_asset_id, status, visibility, rights_status,
                public_streaming_enabled, public_from, public_until, is_listed_publicly
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $movie->execute([
            'Playback Movie',
            'playback-movie-' . uniqid('', true),
            'Synopsis',
            5400,
            $assetId,
            'published',
            $visibility,
            'owned_by_me',
            1,
            gmdate('Y-m-d H:i:s', time() - 3600),
            gmdate('Y-m-d H:i:s', time() + 3600),
            1,
        ]);

        return (int)$pdo->lastInsertId();
    }

    private function makeAccessToken(int $userId, string $role): string
    {
        $payload = ['sub' => $userId, 'role' => $role, 'exp' => time() + 900];
        $payloadB64 = rtrim(strtr(base64_encode((string)json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payloadB64, (string)($_ENV['APP_KEY'] ?? $_ENV['JWT_SECRET'] ?? ''));

        return $payloadB64 . '.' . $signature;
    }

    private function seedCloudflareSigningEnv(): void
    {
        $keyResource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if ($keyResource === false) {
            $this->markTestSkipped('Unable to generate RSA key for playback signing test.');
        }

        $privateKey = '';
        openssl_pkey_export($keyResource, $privateKey);

        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM'] = $privateKey;
        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_ID'] = 'test-kid';
        $_ENV['CLOUDFLARE_STREAM_CUSTOMER_CODE'] = 'test-customer';
    }
}
