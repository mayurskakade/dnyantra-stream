<?php
namespace App\Core;

use App\Exceptions\ValidationException;

class Request {
    private array $attributes = [];
    private ?array $inputCache = null;

    public function method(): string { return $_SERVER['REQUEST_METHOD'] ?? 'GET'; }
    public function path(): string { return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/'; }
    public function input(): array {
        if ($this->inputCache !== null) {
            return $this->inputCache;
        }

        $raw = file_get_contents('php://input');
        $jsonData = [];

        if ($raw !== false && trim($raw) !== '' && $this->shouldParseJson($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new ValidationException(
                    'Malformed JSON body',
                    ['body' => ['Malformed JSON body.']],
                    400
                );
            }

            $jsonData = $decoded;
        }

        $this->inputCache = array_merge($_GET, $_POST, $jsonData);
        return $this->inputCache;
    }

    public function all(): array { return $this->input(); }
    public function header(string $key): ?string { $name='HTTP_'.strtoupper(str_replace('-','_',$key)); return $_SERVER[$name]??null; }
    public function bearerToken(): ?string {
        $h = $this->header('Authorization');
        if (!$h || !str_starts_with($h, 'Bearer ')) return null;
        return trim(substr($h, 7));
    }
    public function setAttribute(string $key, mixed $value): void { $this->attributes[$key] = $value; }
    public function attribute(string $key, mixed $default = null): mixed { return $this->attributes[$key] ?? $default; }
    public function getRouteParam(string $name): ?string {
        $params = $this->attribute('route.params', []);
        $value = $params[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    private function shouldParseJson(string $raw): bool {
        $contentType = strtolower((string) ($this->header('Content-Type') ?? ''));
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        $trimmed = ltrim($raw);
        return $trimmed !== '' && in_array($trimmed[0], ['{', '['], true);
    }
}
