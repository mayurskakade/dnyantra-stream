<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDO;

final class AuthEndpointsTest extends FeatureTestCase
{
    public function testLoginReturns422ForInvalidPayload(): void
    {
        $response = $this->dispatch('POST', '/api/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $this->assertSame(422, $response['status']);
        $this->assertSame('validation_failed', $response['json']['error']['code'] ?? null);
        $this->assertSame('Validation failed', $response['json']['error']['message'] ?? null);
    }

    public function testRefreshReturns401ForMissingToken(): void
    {
        $response = $this->dispatch('POST', '/api/auth/refresh', []);

        $this->assertSame(401, $response['status']);
        $this->assertSame('invalid_credentials', $response['json']['error']['code'] ?? null);
        $this->assertSame('Invalid refresh token', $response['json']['error']['message'] ?? null);
    }

    public function testMeRequiresAuthorizationHeader(): void
    {
        $response = $this->dispatch('GET', '/api/me');

        $this->assertSame(401, $response['status']);
        $this->assertSame('authorization_failed', $response['json']['error']['code'] ?? null);
    }

    public function testLoginHappyPathWhenDatabaseIsAvailable(): void
    {
        $pdo = $this->requireDatabaseForAuthFlow();
        $email = 'agent10+' . uniqid('', true) . '@example.com';
        $password = 'Passw0rd!';
        $this->insertUser($pdo, $email, $password);

        $response = $this->dispatch('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertIsString($response['json']['access_token'] ?? null);
        $this->assertIsString($response['json']['refresh_token'] ?? null);
    }

    public function testWrongPasswordReturns401WhenDatabaseIsAvailable(): void
    {
        $pdo = $this->requireDatabaseForAuthFlow();
        $email = 'agent10+' . uniqid('', true) . '@example.com';
        $this->insertUser($pdo, $email, 'CorrectPassw0rd!');

        $response = $this->dispatch('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'IncorrectPassw0rd!',
        ]);

        $this->assertSame(401, $response['status']);
        $this->assertSame('Invalid credentials', $response['json']['error'] ?? null);
    }

    public function testRefreshRotatesTokenAndRejectsReuseWhenDatabaseIsAvailable(): void
    {
        $pdo = $this->requireDatabaseForAuthFlow();
        $email = 'agent10+' . uniqid('', true) . '@example.com';
        $password = 'Passw0rd!';
        $this->insertUser($pdo, $email, $password);

        $login = $this->dispatch('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $this->assertSame(200, $login['status']);

        $initialRefreshToken = (string)($login['json']['refresh_token'] ?? '');
        $this->assertNotSame('', $initialRefreshToken);

        $refresh = $this->dispatch('POST', '/api/auth/refresh', [
            'refresh_token' => $initialRefreshToken,
        ]);

        $this->assertSame(200, $refresh['status']);
        $this->assertIsString($refresh['json']['refresh_token'] ?? null);

        $reuseAttempt = $this->dispatch('POST', '/api/auth/refresh', [
            'refresh_token' => $initialRefreshToken,
        ]);

        $this->assertSame(401, $reuseAttempt['status']);
        $this->assertSame('Invalid refresh token', $reuseAttempt['json']['error'] ?? null);
    }

    public function testRateLimitCoverageIsBlockedByMissingMiddlewareWiring(): void
    {
        $this->markTestSkipped(
            'TODO(agent-10-followup): Auth rate-limit middleware is not wired yet, cannot assert 429 behavior.'
        );
    }

    private function requireDatabaseForAuthFlow(): PDO
    {
        if (!$this->hasDatabaseConnection()) {
            $this->markTestSkipped(
                'TODO(agent-10-followup): Database unavailable for auth feature tests. Start MySQL and run migrations.'
            );
        }

        $pdo = $this->pdo();
        if (!$pdo instanceof PDO) {
            $this->markTestSkipped(
                'TODO(agent-10-followup): Unable to initialize PDO for auth feature tests.'
            );
        }

        if (!$this->tableExists($pdo, 'users')) {
            $this->markTestSkipped(
                'TODO(agent-10-followup): users table is missing in test DB. Run composer migrate first.'
            );
        }

        return $pdo;
    }

    private function insertUser(PDO $pdo, string $email, string $password): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            'Agent10 Test User',
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            'viewer',
        ]);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);

        return (bool)$stmt->fetchColumn();
    }
}
