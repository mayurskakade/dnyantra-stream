<?php
namespace App\Core;

class Request {
    public function method(): string { return $_SERVER['REQUEST_METHOD'] ?? 'GET'; }
    public function path(): string { return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/'; }
    public function input(): array { $raw=file_get_contents('php://input'); $json=json_decode($raw ?: '{}', true); return array_merge($_GET,$_POST,is_array($json)?$json:[]); }
    public function header(string $key): ?string { $name='HTTP_'.strtoupper(str_replace('-','_',$key)); return $_SERVER[$name]??null; }
    public function bearerToken(): ?string {
        $h = $this->header('Authorization');
        if (!$h || !str_starts_with($h, 'Bearer ')) return null;
        return trim(substr($h, 7));
    }
}
