<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\ErrorHandler;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConfigurationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
        header_remove();
        http_response_code(200);
    }

    public function test_validation_exception_is_rendered_as_structured_api_json(): void
    {
        [$status, $payload] = $this->render('/api/auth/login', new ValidationException(
            'Malformed JSON body',
            ['body' => ['Malformed JSON body.']],
            400
        ));

        $this->assertSame(400, $status);
        $this->assertSame('validation_failed', $payload['error']['code']);
        $this->assertSame('Malformed JSON body', $payload['error']['message']);
        $this->assertSame(['body' => ['Malformed JSON body.']], $payload['error']['details']);
    }

    public function test_authorization_exception_is_rendered_as_structured_api_json(): void
    {
        [$status, $payload] = $this->render('/api/secure', new AuthorizationException('Forbidden', 403));

        $this->assertSame(403, $status);
        $this->assertSame('authorization_failed', $payload['error']['code']);
        $this->assertSame('Forbidden', $payload['error']['message']);
        $this->assertArrayNotHasKey('details', $payload['error']);
    }

    public function test_not_found_exception_is_rendered_as_structured_api_json(): void
    {
        [$status, $payload] = $this->render('/api/unknown', new NotFoundException('Route not found'));

        $this->assertSame(404, $status);
        $this->assertSame('not_found', $payload['error']['code']);
        $this->assertSame('Route not found', $payload['error']['message']);
    }

    public function test_configuration_exception_is_rendered_as_structured_api_json(): void
    {
        [$status, $payload] = $this->render('/api/me', new ConfigurationException('Missing JWT_SECRET'));

        $this->assertSame(500, $status);
        $this->assertSame('configuration_error', $payload['error']['code']);
        $this->assertSame('Missing JWT_SECRET', $payload['error']['message']);
    }

    public function test_generic_throwable_is_masked_as_internal_server_error(): void
    {
        [$status, $payload] = $this->render('/api/me', new RuntimeException('db connection failed'));

        $this->assertSame(500, $status);
        $this->assertSame('internal_server_error', $payload['error']['code']);
        $this->assertSame('Internal Server Error', $payload['error']['message']);
    }

    public function test_admin_path_renders_html_error_response(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/login';
        http_response_code(200);
        ob_start();
        (new ErrorHandler())->handleException(new AuthorizationException('<script>alert(1)</script>', 403));
        $html = (string) ob_get_clean();

        $this->assertSame(403, http_response_code());
        $this->assertStringContainsString('<h1>Request Error</h1>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    private function render(string $uri, \Throwable $throwable): array
    {
        $_SERVER['REQUEST_URI'] = $uri;
        http_response_code(200);

        ob_start();
        (new ErrorHandler())->handleException($throwable);
        $body = (string) ob_get_clean();

        $decoded = json_decode($body, true);
        $payload = is_array($decoded) ? $decoded : [];

        return [http_response_code(), $payload];
    }
}
