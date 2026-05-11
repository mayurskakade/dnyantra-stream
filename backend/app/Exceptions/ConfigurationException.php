<?php
namespace App\Exceptions;

use RuntimeException;

class ConfigurationException extends RuntimeException {
    public function __construct(
        string $message = 'Configuration is invalid',
        private readonly string $errorCode = 'configuration_error'
    ) {
        parent::__construct($message, 500);
    }

    public function status(): int {
        return 500;
    }

    public function errorCode(): string {
        return $this->errorCode;
    }
}
