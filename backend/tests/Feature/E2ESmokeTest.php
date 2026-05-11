<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDO;

final class E2ESmokeTest extends FeatureTestCase
{
    public function testHappyPathCoversLoginCatalogPlaybackAndProgress(): void
    {
        $pdo = $this->requireDatabaseForSmokeFlow();
        $this->seedCloudflareSigningEnv();

        $email = 'smoke+' . uniqid('', true) . '@example.com';
        $password = 'SmokePassw0rd!';
        $userId = $this->insertUser($pdo, $email, $password);
        $movie = $this->insertPublishedAuthenticatedMovie($pdo);

        $login = $this->dispatch('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $this->assertSame(200, $login['status']);
        $this->assertIsString($login['json']['access_token'] ?? null);

        $accessToken = (string)$login['json']['access_token'];

        $home = $this->dispatch('GET', '/api/home', [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
        $this->assertSame(200, $home['status']);
        $this->assertArrayHasKey('rails', $home['json'] ?? []);
        $this->assertArrayHasKey('continue_watching', $home['json'] ?? []);

        $movies = $this->dispatch('GET', '/api/movies', [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
        $this->assertSame(200, $movies['status']);
        $this->assertIsArray($movies['json']['data'] ?? null);

        $movieDetail = $this->dispatch('GET', '/api/movies/' . $movie['slug'], [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
        $this->assertSame(200, $movieDetail['status']);
        $this->assertSame($movie['slug'], $movieDetail['json']['slug'] ?? null);

        $playback = $this->dispatch('POST', '/api/playback-sessions', [
            'playable_type' => 'movie',
            'playable_id' => $movie['id'],
        ], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
        $this->assertSame(200, $playback['status']);
        $this->assertIsString($playback['json']['playback_url'] ?? null);
        $this->assertIsInt($playback['json']['expires_at'] ?? null);
        $this->assertIsString($playback['json']['session_id'] ?? null);

        $progress = $this->dispatch('POST', '/api/watch-progress', [
            'playable_type' => 'movie',
            'playable_id' => $movie['id'],
            'position_seconds' => 120,
            'duration_seconds' => 3600,
        ], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
        $this->assertSame(200, $progress['status']);
        $this->assertSame(120, $progress['json']['position_seconds'] ?? null);
        $this->assertFalse((bool)($progress['json']['completed'] ?? true));

        $continueWatching = $this->dispatch('GET', '/api/continue-watching', [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);
        $this->assertSame(200, $continueWatching['status']);
        $this->assertIsArray($continueWatching['json']['data'] ?? null);

        $dbRow = $pdo->prepare(
            'SELECT user_id, playable_type, playable_id, position_seconds FROM watch_progress WHERE user_id = ? AND playable_type = ? AND playable_id = ? LIMIT 1'
        );
        $dbRow->execute([$userId, 'movie', $movie['id']]);
        $row = $dbRow->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame((string)$userId, (string)$row['user_id']);
        $this->assertSame('movie', (string)$row['playable_type']);
        $this->assertSame((string)$movie['id'], (string)$row['playable_id']);
        $this->assertSame('120', (string)$row['position_seconds']);
    }

    private function requireDatabaseForSmokeFlow(): PDO
    {
        if (!$this->hasDatabaseConnection()) {
            $this->markTestSkipped(
                'TODO(agent-10-followup): Database unavailable for E2E smoke. Start MySQL and run composer migrate.'
            );
        }

        $pdo = $this->pdo();
        if (!$pdo instanceof PDO) {
            $this->markTestSkipped('TODO(agent-10-followup): Unable to initialize PDO for E2E smoke.');
        }

        $requiredTables = ['users', 'movies', 'media_assets', 'watch_progress', 'playback_sessions'];
        foreach ($requiredTables as $table) {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            if (!$stmt->fetchColumn()) {
                $this->markTestSkipped('TODO(agent-10-followup): Missing required table for E2E smoke: ' . $table);
            }
        }

        return $pdo;
    }

    private function insertUser(PDO $pdo, string $email, string $password): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            'E2E Smoke User',
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            'viewer',
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * @return array{id:int,slug:string}
     */
    private function insertPublishedAuthenticatedMovie(PDO $pdo): array
    {
        $asset = $pdo->prepare(
            'INSERT INTO media_assets (provider, provider_uid, status, require_signed_playback, original_filename) VALUES (?, ?, ?, ?, ?)'
        );
        $asset->execute([
            'cloudflare_stream',
            'smoke-uid-' . uniqid('', true),
            'ready',
            1,
            'smoke.mp4',
        ]);
        $assetId = (int)$pdo->lastInsertId();

        $slug = 'smoke-movie-' . uniqid('', true);
        $movie = $pdo->prepare(
            'INSERT INTO movies (
                title, slug, synopsis, duration_seconds, media_asset_id, poster_url, banner_url,
                release_year, status, visibility, rights_status, public_streaming_enabled, public_from, public_until, is_listed_publicly
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $movie->execute([
            'Smoke Movie',
            $slug,
            'Smoke synopsis',
            3600,
            $assetId,
            'https://cdn.example.com/poster.jpg',
            'https://cdn.example.com/banner.jpg',
            2024,
            'published',
            'authenticated',
            'owned_by_me',
            1,
            gmdate('Y-m-d H:i:s', time() - 3600),
            gmdate('Y-m-d H:i:s', time() + 3600),
            1,
        ]);

        return [
            'id' => (int)$pdo->lastInsertId(),
            'slug' => $slug,
        ];
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
        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_ID'] = 'smoke-kid';
        $_ENV['CLOUDFLARE_STREAM_CUSTOMER_CODE'] = 'smoke-customer';
    }
}
