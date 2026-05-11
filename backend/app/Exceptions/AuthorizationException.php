<?php
namespace App\Exceptions;

use RuntimeException;

class AuthorizationException extends RuntimeException {
    public function __construct(
        string $message = 'Unauthorized',
        private readonly int $status = 403,
        private readonly string $errorCode = 'authorization_failed'
    ) {
        parent::__construct($message, $status);
    }

    public function status(): int {
        return $this->status;
    }

    public function errorCode(): string {
        return $this->errorCode;
    }
}
