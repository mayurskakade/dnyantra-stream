<?php
namespace App\Core;

class Response {
    public static function json(array $data,int $status=200): void { http_response_code($status); header('Content-Type: application/json'); echo json_encode($data); }
    public static function view(string $html,int $status=200): void { http_response_code($status); echo $html; }
}
