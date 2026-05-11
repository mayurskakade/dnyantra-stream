<?php
namespace App\Exceptions;

use RuntimeException;

class NotFoundException extends RuntimeException {
    public function __construct(
        string $message = 'Not Found',
        private readonly string $errorCode = 'not_found'
    ) {
        parent::__construct($message, 404);
    }

    public function status(): int {
        return 404;
    }

    public function errorCode(): string {
        return $this->errorCode;
    }
}
