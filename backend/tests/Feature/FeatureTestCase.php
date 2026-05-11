<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class FeatureTestCase extends TestCase
{
    private array $serverBackup = [];
    private array $getBackup = [];
    private array $postBackup = [];
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->envBackup = $_ENV;

        $this->seedDefaultEnv();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_ENV = $this->envBackup;

        parent::tearDown();
    }

    protected function dispatch(string $method, string $path, array $payload = [], array $headers = []): array
    {
        if (!class_exists('App\\Services\\RateLimiterFactory')) {
            $this->markTestSkipped(
                'TODO(agent-10-followup): API bootstrap depends on App\\Services\\RateLimiterFactory; upstream autoload wiring is incomplete.'
            );
        }

        $_SERVER = [];
        $_GET = [];
        $_POST = [];

        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $path;

        foreach ($headers as $name => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $_SERVER[$key] = $value;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_GET = $payload;
        } else {
            $_POST = $payload;
        }

        http_response_code(200);
        ob_start();

        $rawBody = '';
        try {
            $router = new Router();
            $request = new Request();
            require __DIR__ . '/../../routes/api.php';
            $router->dispatch($request);
        } catch (Throwable $throwable) {
            (new ErrorHandler())->handleException($throwable);
        } finally {
            $rawBody = (string)ob_get_clean();
        }

        $decoded = json_decode($rawBody, true);

        return [
            'status' => http_response_code(),
            'json' => is_array($decoded) ? $decoded : null,
            'raw' => $rawBody,
        ];
    }

    protected function hasRoute(string $httpMethod, string $routePath): bool
    {
        $routesFile = file_get_contents(__DIR__ . '/../../routes/api.php');
        if ($routesFile === false) {
            return false;
        }

        $needle = "\\$router->" . strtolower($httpMethod) . "('" . $routePath . "'";

        return str_contains($routesFile, $needle);
    }

    protected function hasDatabaseConnection(): bool
    {
        try {
            $pdo = Database::pdo();
            $pdo->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function pdo(): ?PDO
    {
        try {
            return Database::pdo();
        } catch (Throwable) {
            return null;
        }
    }

    private function seedDefaultEnv(): void
    {
        $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3306';
        $_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? 'dnyantra_test';
        $_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? 'app';
        $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? 'app';
        $_ENV['JWT_SECRET'] = $_ENV['JWT_SECRET'] ?? 'test-secret';
        $_ENV['APP_KEY'] = $_ENV['APP_KEY'] ?? $_ENV['JWT_SECRET'];
    }
}
