<?php
namespace App\Services;

class TwoFactorService {
    public function isEnrollmentRequired(array $user): bool {
        $totpSecret = $user['totp_secret'] ?? null;
        return is_string($totpSecret) && $totpSecret !== '';
    }

    public function verifyCode(array $user, string $code): bool {
        // TODO(agent-02-followup): Implement TOTP verification flow.
        return false;
    }
}
