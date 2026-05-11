<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDO;

final class WatchProgressEndpointsTest extends FeatureTestCase
{
    public function testWatchProgressRoutesRequireAuth(): void
    {
        $response = $this->dispatch('POST', '/api/watch-progress', [
            'playable_type' => 'movie',
            'playable_id' => 1,
            'position_seconds' => 1,
            'duration_seconds' => 10,
        ]);

        $this->assertSame(401, $response['status']);
        $this->assertSame('authorization_failed', $response['json']['error']['code'] ?? null);
    }

    public function testUpsertIsIdempotentAndClearDeletesRowWhenDatabaseIsAvailable(): void
    {
        $pdo = $this->requireWatchProgressDatabase();
        $userId = $this->insertUser($pdo);

        for ($i = 1; $i <= 100; $i++) {
            $response = $this->dispatch('POST', '/api/watch-progress', [
                'playable_type' => 'movie',
                'playable_id' => 777,
                'position_seconds' => $i,
                'duration_seconds' => 100,
            ], [
                'Authorization' => 'Bearer ' . $this->makeAccessToken($userId, 'viewer'),
            ]);

            $this->assertSame(200, $response['status']);
        }

        $stmt = $pdo->prepare('SELECT position_seconds, completed FROM watch_progress WHERE user_id = ? AND playable_type = ? AND playable_id = ?');
        $stmt->execute([$userId, 'movie', 777]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertSame('100', (string)$rows[0]['position_seconds']);
        $this->assertSame('1', (string)$rows[0]['completed']);

        $clear = $this->dispatch('POST', '/api/watch-progress/clear', [
            'playable_type' => 'movie',
            'playable_id' => 777,
        ], [
            'Authorization' => 'Bearer ' . $this->makeAccessToken($userId, 'viewer'),
        ]);

        $this->assertSame(200, $clear['status']);

        $check = $pdo->prepare('SELECT COUNT(*) FROM watch_progress WHERE user_id = ? AND playable_type = ? AND playable_id = ?');
        $check->execute([$userId, 'movie', 777]);
        $this->assertSame('0', (string)$check->fetchColumn());
    }

    public function testMarkWatchedSetsCompletionWhenDatabaseIsAvailable(): void
    {
        $pdo = $this->requireWatchProgressDatabase();
        $userId = $this->insertUser($pdo);

        $seed = $this->dispatch('POST', '/api/watch-progress', [
            'playable_type' => 'episode',
            'playable_id' => 990,
            'position_seconds' => 40,
            'duration_seconds' => 100,
        ], [
            'Authorization' => 'Bearer ' . $this->makeAccessToken($userId, 'viewer'),
        ]);
        $this->assertSame(200, $seed['status']);

        $mark = $this->dispatch('POST', '/api/watch-progress/mark-watched', [
            'playable_type' => 'episode',
            'playable_id' => 990,
        ], [
            'Authorization' => 'Bearer ' . $this->makeAccessToken($userId, 'viewer'),
        ]);

        $this->assertSame(200, $mark['status']);
        $this->assertTrue((bool)($mark['json']['completed'] ?? false));

        $stmt = $pdo->prepare('SELECT completed FROM watch_progress WHERE user_id = ? AND playable_type = ? AND playable_id = ? LIMIT 1');
        $stmt->execute([$userId, 'episode', 990]);

        $this->assertSame('1', (string)$stmt->fetchColumn());
    }

    private function requireWatchProgressDatabase(): PDO
    {
        if (!$this->hasDatabaseConnection()) {
            $this->markTestSkipped('Database unavailable for watch-progress feature tests.');
        }

        $pdo = $this->pdo();
        if (!$pdo instanceof PDO) {
            $this->markTestSkipped('Unable to initialize PDO for watch-progress feature tests.');
        }

        foreach (['users', 'watch_progress'] as $table) {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            if (!$stmt->fetchColumn()) {
                $this->markTestSkipped('Missing table for watch-progress tests: ' . $table);
            }
        }

        return $pdo;
    }

    private function insertUser(PDO $pdo): int
    {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([
            'Watch Progress User',
            'watch+' . uniqid('', true) . '@example.com',
            password_hash('secret', PASSWORD_DEFAULT),
            'viewer',
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
}
