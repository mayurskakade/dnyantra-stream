<?php
namespace App\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException {
    public function __construct(
        string $message = 'Validation failed',
        private readonly array $details = [],
        private readonly int $status = 422,
        private readonly string $errorCode = 'validation_failed'
    ) {
        parent::__construct($message, $status);
    }

    public function details(): array {
        return $this->details;
    }

    public function status(): int {
        return $this->status;
    }

    public function errorCode(): string {
        return $this->errorCode;
    }
}
