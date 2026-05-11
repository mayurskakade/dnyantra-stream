<?php
namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use PDO;
use Throwable;

class AuthService {
    private const ACCESS_TOKEN_TTL_SECONDS = 900;
    private const REFRESH_TOKEN_TTL_DAYS = 30;

    public function __construct(private readonly ?PDO $pdo = null) {}

    private function pdo(): PDO {
        return $this->pdo ?? Database::pdo();
    }

    public function login(string $email, string $password): ?array {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password_hash'])) return null;
        if ((int)($user['is_active'] ?? 1) !== 1) {
            throw new AuthorizationException('Account is inactive', 403, 'account_inactive');
        }

        $tokens = $this->issueTokenPair((int)$user['id'], (string)$user['role']);

        return [
            'user' => $this->publicUser($user),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
        ];
    }

    public function refresh(string $refreshToken): ?array {
        if ($refreshToken === '') {
            return null;
        }

        $pdo = $this->pdo();
        $now = $this->now();
        $newRefreshToken = null;
        $row = null;

        $pdo->beginTransaction();
        try {
            $lookup = $pdo->prepare('SELECT rt.id, rt.user_id, u.name, u.email, u.role, u.is_active FROM refresh_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.token_hash = ? AND rt.revoked_at IS NULL AND rt.expires_at > ? LIMIT 1');
            $lookup->execute([hash('sha256', $refreshToken), $now]);
            $row = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)($row['is_active'] ?? 0) !== 1) {
                $pdo->rollBack();
                return null;
            }

            $revoke = $pdo->prepare('UPDATE refresh_tokens SET revoked_at = ? WHERE id = ?');
            $revoke->execute([$now, (int)$row['id']]);

            $newRefreshToken = $this->randomToken();
            $insert = $pdo->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)');
            $insert->execute([
                (int)$row['user_id'],
                hash('sha256', $newRefreshToken),
                $this->formatDate(time() + (self::REFRESH_TOKEN_TTL_DAYS * 86400)),
                $now,
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if (!is_array($row) || !is_string($newRefreshToken)) {
            return null;
        }

        return [
            'access_token' => $this->makeAccessToken((int)$row['user_id'], (string)$row['role']),
            'refresh_token' => $newRefreshToken,
            'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
            'user' => $this->publicUser($row),
        ];
    }

    public function validateAccessToken(?string $token): ?array {
        if (!$token) return null;
        $parts = explode('.', $token);
        if (count($parts) !== 2) return null;
        [$payloadB64, $sig] = $parts;
        $calc = hash_hmac('sha256', $payloadB64, $this->jwtSecret());
        if (!hash_equals($calc, $sig)) return null;
        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')) ?: '', true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) return null;
        return ['id'=>(int)$payload['sub'], 'role'=>$payload['role'] ?? 'viewer'];
    }

    public function logout(string $refreshToken): void {
        $this->pdo()->prepare('UPDATE refresh_tokens SET revoked_at = ? WHERE token_hash = ?')->execute([$this->now(), hash('sha256', $refreshToken)]);
    }

    private function makeAccessToken(int $userId, string $role): string {
        $payload = ['sub'=>$userId,'role'=>$role,'exp'=>time()+self::ACCESS_TOKEN_TTL_SECONDS];
        $b64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $b64, $this->jwtSecret());
        return $b64.'.'.$sig;
    }

    private function issueTokenPair(int $userId, string $role): array {
        $refreshToken = $this->randomToken();
        $now = $this->now();
        $this->pdo()->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)')
            ->execute([
                $userId,
                hash('sha256', $refreshToken),
                $this->formatDate(time() + (self::REFRESH_TOKEN_TTL_DAYS * 86400)),
                $now,
            ]);

        return [
            'access_token' => $this->makeAccessToken($userId, $role),
            'refresh_token' => $refreshToken,
        ];
    }

    private function jwtSecret(): string {
        return (string)($_ENV['APP_KEY'] ?? $_ENV['JWT_SECRET'] ?? '');
    }

    private function randomToken(): string {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function publicUser(array $u): array {
        return [
            'id' => isset($u['id']) ? (int)$u['id'] : (int)($u['user_id'] ?? 0),
            'name' => (string)($u['name'] ?? ''),
            'email' => (string)($u['email'] ?? ''),
            'role' => (string)($u['role'] ?? 'viewer'),
        ];
    }

    private function now(): string {
        return $this->formatDate(time());
    }

    private function formatDate(int $timestamp): string {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
