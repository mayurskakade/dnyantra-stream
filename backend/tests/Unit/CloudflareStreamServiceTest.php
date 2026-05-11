<?php
namespace Tests\Unit;

use App\Exceptions\ConfigurationException;
use App\Services\CloudflareStreamService;
use App\Support\HttpClient;
use PHPUnit\Framework\TestCase;

class CloudflareStreamServiceTest extends TestCase {
    private array $envBackup = [];

    protected function setUp(): void {
        $this->envBackup = [
            'CLOUDFLARE_ACCOUNT_ID' => $_ENV['CLOUDFLARE_ACCOUNT_ID'] ?? null,
            'CLOUDFLARE_STREAM_API_TOKEN' => $_ENV['CLOUDFLARE_STREAM_API_TOKEN'] ?? null,
            'CLOUDFLARE_STREAM_SIGNING_KEY_PEM' => $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM'] ?? null,
            'CLOUDFLARE_STREAM_SIGNING_KEY_ID' => $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_ID'] ?? null,
            'CLOUDFLARE_STREAM_KEY_ID' => $_ENV['CLOUDFLARE_STREAM_KEY_ID'] ?? null,
        ];
    }

    protected function tearDown(): void {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
                continue;
            }
            $_ENV[$key] = $value;
        }
    }

    public function test_creates_rs256_jwt_with_exact_header_and_verifiable_signature(): void {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        $this->assertNotFalse($privateKey);

        $privateKeyPem = '';
        $this->assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));
        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $publicKeyPem = $details['key'];

        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM'] = str_replace("\n", '\\n', $privateKeyPem);
        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_ID'] = 'kid-test';

        $service = new CloudflareStreamService();
        $expiresAt = strtotime('2030-01-01T00:00:00+00:00');
        $this->assertIsInt($expiresAt);

        $token = $service->createSignedPlaybackToken('video-123', (int)$expiresAt);
        $parts = explode('.', $token);

        $this->assertCount(3, $parts);

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        $this->assertSame(['alg' => 'RS256', 'kid' => 'kid-test', 'typ' => 'JWT'], $header);
        $this->assertSame('video-123', $payload['sub'] ?? null);
        $this->assertSame((int)$expiresAt, $payload['exp'] ?? null);

        $verified = openssl_verify(
            $parts[0] . '.' . $parts[1],
            $this->base64UrlDecode($parts[2]),
            $publicKeyPem,
            OPENSSL_ALGO_SHA256
        );
        $this->assertSame(1, $verified);
    }

    public function test_fails_closed_when_signing_key_is_missing(): void {
        unset($_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM']);
        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_ID'] = 'kid-test';

        $service = new CloudflareStreamService();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Missing required environment variable: CLOUDFLARE_STREAM_SIGNING_KEY_PEM');

        $service->createSignedPlaybackToken('video-123', time() + 1800);
    }

    public function test_fails_closed_when_signing_key_is_invalid(): void {
        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM'] = 'invalid-key';
        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_ID'] = 'kid-test';

        $service = new CloudflareStreamService();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid CLOUDFLARE_STREAM_SIGNING_KEY_PEM');

        $service->createSignedPlaybackToken('video-123', time() + 1800);
    }

    public function test_create_direct_upload_url_uses_bearer_auth_and_expected_endpoint(): void {
        $_ENV['CLOUDFLARE_ACCOUNT_ID'] = 'account-123';
        $_ENV['CLOUDFLARE_STREAM_API_TOKEN'] = 'token-xyz';

        $calls = [];
        $httpClient = new HttpClient(function (string $method, string $url, array $headers, ?string $body) use (&$calls): array {
            $calls[] = compact('method', 'url', 'headers', 'body');
            return [
                'status' => 200,
                'body' => json_encode([
                    'success' => true,
                    'result' => [
                        'uploadURL' => 'https://upload.example/file',
                        'uid' => 'stream-uid-123',
                    ],
                ], JSON_UNESCAPED_SLASHES),
            ];
        });

        $service = new CloudflareStreamService($httpClient);
        $result = $service->createDirectUploadUrl(['meta' => ['title' => 'My video']]);

        $this->assertSame(['upload_url' => 'https://upload.example/file', 'uid' => 'stream-uid-123'], $result);
        $this->assertCount(1, $calls);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame(
            'https://api.cloudflare.com/client/v4/accounts/account-123/stream/direct_upload',
            $calls[0]['url']
        );
        $this->assertSame('Bearer token-xyz', $calls[0]['headers']['Authorization'] ?? null);
        $this->assertSame('application/json', $calls[0]['headers']['Content-Type'] ?? null);
    }

    public function test_create_direct_upload_url_throws_when_api_token_is_missing(): void {
        $_ENV['CLOUDFLARE_ACCOUNT_ID'] = 'account-123';
        unset($_ENV['CLOUDFLARE_STREAM_API_TOKEN']);

        $service = new CloudflareStreamService(new HttpClient(
            fn (): array => ['status' => 200, 'body' => '{}']
        ));

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Missing required environment variable: CLOUDFLARE_STREAM_API_TOKEN');

        $service->createDirectUploadUrl([]);
    }

    private function base64UrlDecode(string $value): string {
        $padded = strtr($value, '-_', '+/');
        $padding = strlen($padded) % 4;
        if ($padding > 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        return (string)base64_decode($padded);
    }
}
