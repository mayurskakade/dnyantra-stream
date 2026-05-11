<?php
namespace App\Support;

class LogRedactor {
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'authorization',
        'cookie',
        'access_token',
        'refresh_token',
        'token',
        'token_hash',
        'session_token',
        'csrf',
    ];

    public function redact(array $context): array {
        return $this->redactValue($context);
    }

    private function redactValue(mixed $value): mixed {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $nestedValue) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            $redacted[$key] = $this->redactValue($nestedValue);
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool {
        $normalized = strtolower(trim($key));
        return in_array($normalized, self::SENSITIVE_KEYS, true);
    }
}
