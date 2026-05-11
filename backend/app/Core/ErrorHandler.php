<?php
namespace App\Core;

use App\Exceptions\AuthorizationException;
use App\Exceptions\ConfigurationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Support\LogRedactor;
use Throwable;

class ErrorHandler {
    public function __construct(private readonly LogRedactor $logRedactor = new LogRedactor()) {}

    public function register(): void {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
    }

    public function handleError(int $severity, string $message, string $file, int $line): never {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleException(Throwable $exception): void {
        $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

        if ($this->isApiPath($path)) {
            $this->renderApiError($exception);
            return;
        }

        $this->renderAdminError($exception);
    }

    private function isApiPath(string $path): bool {
        return $path === '/api'
            || $path === '/public'
            || str_starts_with($path, '/api/')
            || str_starts_with($path, '/public/');
    }

    private function renderApiError(Throwable $exception): void {
        [$status, $code, $message, $details] = $this->normalize($exception);
        Response::json([
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ], fn($value) => $value !== null),
        ], $status);
    }

    private function renderAdminError(Throwable $exception): void {
        [$status, , $message] = $this->normalize($exception);
        $title = $status >= 500 ? 'Server Error' : 'Request Error';
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        Response::view("<h1>{$title}</h1><p>{$safeMessage}</p>", $status);
    }

    private function normalize(Throwable $exception): array {
        if ($exception instanceof ValidationException) {
            return [$exception->status(), $exception->errorCode(), $exception->getMessage(), $exception->details()];
        }

        if ($exception instanceof AuthorizationException) {
            return [$exception->status(), $exception->errorCode(), $exception->getMessage(), null];
        }

        if ($exception instanceof NotFoundException) {
            return [$exception->status(), $exception->errorCode(), $exception->getMessage(), null];
        }

        if ($exception instanceof ConfigurationException) {
            return [$exception->status(), $exception->errorCode(), $exception->getMessage(), null];
        }

        if (($exception->getCode() >= 400) && ($exception->getCode() <= 499)) {
            return [(int) $exception->getCode(), 'request_failed', $exception->getMessage(), null];
        }

        $this->logUnhandledException($exception);
        return [500, 'internal_server_error', 'Internal Server Error', null];
    }

    private function logUnhandledException(Throwable $exception): void {
        $payload = [
            'event' => 'unhandled_exception',
            'context' => $this->logRedactor->redact([
                'exception' => [
                    'type' => $exception::class,
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
                'request' => [
                    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                    'uri' => $_SERVER['REQUEST_URI'] ?? null,
                    'query' => $_GET ?? [],
                    'input' => $_POST ?? [],
                    'headers' => $this->extractHeadersFromServer(),
                ],
            ]),
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        error_log($encoded === false ? (string)$exception : $encoded);
    }

    private function extractHeadersFromServer(): array {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$headerName] = $value;
        }

        return $headers;
    }
}
