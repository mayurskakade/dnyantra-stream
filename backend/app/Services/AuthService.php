<?php
namespace App\Services;

use App\Core\Database;
use PDO;

class AuthService {
    public function login(string $email, string $password): ?array {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password_hash'])) return null;

        $accessToken = $this->makeAccessToken((int)$user['id'], $user['role']);
        $refreshToken = $this->randomToken();
        Database::pdo()->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())')
            ->execute([(int)$user['id'], hash('sha256', $refreshToken)]);

        return ['user'=>$this->publicUser($user),'access_token'=>$accessToken,'refresh_token'=>$refreshToken,'expires_in'=>3600];
    }

    public function refresh(string $refreshToken): ?array {
        $stmt = Database::pdo()->prepare('SELECT rt.user_id, u.name, u.email, u.role FROM refresh_tokens rt JOIN users u ON u.id=rt.user_id WHERE rt.token_hash=? AND rt.revoked_at IS NULL AND rt.expires_at > NOW() AND u.is_active = 1 LIMIT 1');
        $stmt->execute([hash('sha256', $refreshToken)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return ['access_token'=>$this->makeAccessToken((int)$row['user_id'], $row['role']),'expires_in'=>3600,'user'=>$this->publicUser($row)];
    }

    public function validateAccessToken(?string $token): ?array {
        if (!$token) return null;
        $parts = explode('.', $token);
        if (count($parts) !== 2) return null;
        [$payloadB64, $sig] = $parts;
        $calc = hash_hmac('sha256', $payloadB64, $_ENV['APP_KEY'] ?? '');
        if (!hash_equals($calc, $sig)) return null;
        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')) ?: '', true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) return null;
        return ['id'=>(int)$payload['sub'], 'role'=>$payload['role'] ?? 'viewer'];
    }

    public function logout(string $refreshToken): void {
        Database::pdo()->prepare('UPDATE refresh_tokens SET revoked_at=NOW() WHERE token_hash=?')->execute([hash('sha256', $refreshToken)]);
    }

    private function makeAccessToken(int $userId, string $role): string {
        $payload = ['sub'=>$userId,'role'=>$role,'exp'=>time()+3600];
        $b64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $b64, $_ENV['APP_KEY'] ?? '');
        return $b64.'.'.$sig;
    }
    private function randomToken(): string { return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); }
    private function publicUser(array $u): array { return ['id'=>(int)$u['id'] ?? (int)$u['user_id'], 'name'=>$u['name'], 'email'=>$u['email'], 'role'=>$u['role']]; }
}
