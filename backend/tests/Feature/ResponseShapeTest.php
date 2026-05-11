<?php
namespace Tests\Feature;

use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Throwable;

class ResponseShapeTest extends TestCase {
    private const FORBIDDEN_KEYS = [
        'provider_uid',
        'storage_key',
        'rights_status',
        'public_streaming_enabled',
        'session_token_hash',
        'token_hash',
    ];

    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function test_public_and_catalog_responses_do_not_expose_sensitive_keys(): void {
        $cases = [
            ['GET', '/api/home', []],
            ['GET', '/api/categories', []],
            ['GET', '/api/genres', []],
            ['POST', '/api/auth/login', ['email' => 'not-an-email', 'password' => 'x']],
            ['POST', '/api/auth/refresh', ['refresh_token' => '']],
            ['POST', '/public/playback-sessions', ['playable_type' => 'bad', 'playable_id' => 0]],
        ];

        foreach ($cases as [$method, $uri, $input]) {
            [$status, $body] = $this->dispatchApi($method, $uri, $input);
            $this->assertIsInt($status);
            $this->assertNotSame('', trim($body), "Expected JSON response body for {$method} {$uri}");

            $decoded = json_decode($body, true);
            $this->assertIsArray($decoded, "Expected JSON response for {$method} {$uri}");

            $keys = $this->collectKeys($decoded);
            foreach (self::FORBIDDEN_KEYS as $forbidden) {
                $this->assertNotContains($forbidden, $keys, "Forbidden key {$forbidden} leaked in {$method} {$uri}");
            }
        }
    }

    private function dispatchApi(string $method, string $uri, array $input): array {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = $input;

        $router = new Router();
        $request = new Request();
        require __DIR__ . '/../../routes/api.php';

        $errorHandler = new ErrorHandler();

        ob_start();
        try {
            $router->dispatch($request);
        } catch (Throwable $exception) {
            $errorHandler->handleException($exception);
        }
        $body = (string)ob_get_clean();

        return [http_response_code(), $body];
    }

    private function collectKeys(array $value): array {
        $keys = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $keys[] = strtolower($key);
            }
            if (is_array($item)) {
                $keys = array_merge($keys, $this->collectKeys($item));
            }
        }

        return array_values(array_unique($keys));
    }
}
