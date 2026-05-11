<?php
namespace App\Services;

class CsrfService {
    private const TOKEN_KEY = '_csrf_token';

    public function __construct(private readonly SessionService $session = new SessionService()) {}

    public function token(): string {
        $this->session->start();
        $existing = $_SESSION[self::TOKEN_KEY] ?? null;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY] = $token;
        return $token;
    }

    public function validate(string $submitted): bool {
        $this->session->start();
        $token = $_SESSION[self::TOKEN_KEY] ?? null;
        if (!is_string($token) || $token === '' || $submitted === '') {
            return false;
        }

        return hash_equals($token, $submitted);
    }
}
