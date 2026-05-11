<?php
namespace App\Support;

use App\Services\CsrfService;
use RuntimeException;

class View {
    public static function render(string $template, array $data = []): string {
        $viewFile = __DIR__ . '/../Views/' . ltrim($template, '/') . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $viewFile;
        return (string)ob_get_clean();
    }
}

if (!function_exists(__NAMESPACE__ . '\\e')) {
    function e(mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists(__NAMESPACE__ . '\\csrf_field')) {
    function csrf_field(): string {
        $token = (new CsrfService())->token();
        return '<input type="hidden" name="_csrf" value="' . e($token) . '">';
    }
}
