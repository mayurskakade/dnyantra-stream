<?php
namespace App\Services;

class SessionService {
    private const ACTIVITY_KEY = 'last_activity_at';
    private const ADMIN_USER_KEY = 'admin_user';
    private bool $started = false;

    public function start(): void {
        if ($this->started) {
            return;
        }

        $isProduction = (($_ENV['APP_ENV'] ?? '') === 'production');
        $cookieParams = session_get_cookie_params();
        $targetParams = [
            'lifetime' => 0,
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => $isProduction,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $active = session_get_cookie_params();
            $sameSite = strtolower((string)($active['samesite'] ?? ''));
            $needsRestart = (bool)($active['secure'] ?? false) !== $targetParams['secure']
                || (bool)($active['httponly'] ?? false) !== $targetParams['httponly']
                || $sameSite !== 'lax';
            if ($needsRestart) {
                session_write_close();
                session_set_cookie_params($targetParams);
                session_start();
            }
        } else {
            session_set_cookie_params($targetParams);
            session_start();
        }

        $this->started = true;
        $this->enforceIdleTimeout();
    }

    public function regenerateId(): void {
        $this->start();
        session_regenerate_id(true);
        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    public function setAdminUser(array $user): void {
        $this->start();
        $_SESSION[self::ADMIN_USER_KEY] = [
            'id' => (int)($user['id'] ?? 0),
            'email' => (string)($user['email'] ?? ''),
            'role' => (string)($user['role'] ?? ''),
        ];
        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    public function adminUser(): ?array {
        $this->start();
        $user = $_SESSION[self::ADMIN_USER_KEY] ?? null;
        if (!is_array($user) || ($user['id'] ?? 0) <= 0) {
            return null;
        }

        $_SESSION[self::ACTIVITY_KEY] = time();
        return $user;
    }

    public function clear(): void {
        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    private function enforceIdleTimeout(): void {
        $now = time();
        $lifetimeMinutes = max(1, (int)($_ENV['SESSION_LIFETIME_MINUTES'] ?? 30));
        $ttlSeconds = $lifetimeMinutes * 60;
        $lastActivity = (int)($_SESSION[self::ACTIVITY_KEY] ?? $now);

        if (($now - $lastActivity) > $ttlSeconds) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION[self::ACTIVITY_KEY] = $now;
    }
}
