<?php
namespace Tests\Unit;

use App\Services\CloudflareStreamService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CloudflareStreamServiceTest extends TestCase {
    private array $envBackup = [];

    protected function setUp(): void {
        $this->envBackup = [
            'CLOUDFLARE_STREAM_SIGNING_KEY_PEM' => $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM'] ?? null,
            'CLOUDFLARE_STREAM_KEY_ID' => $_ENV['CLOUDFLARE_STREAM_KEY_ID'] ?? null,
            'CLOUDFLARE_STREAM_SIGNING_KEY_ID' => $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_ID'] ?? null,
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

    public function test_creates_rs256_jwt_with_expected_shape_and_signature(): void {
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

        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM'] = $privateKeyPem;
        $_ENV['CLOUDFLARE_STREAM_KEY_ID'] = 'kid-test';

        $service = new CloudflareStreamService();
        $expiresAt = new DateTimeImmutable('2030-01-01T00:00:00+00:00');

        $token = $service->createSignedPlaybackToken('video-123', $expiresAt);
        $parts = explode('.', $token);

        $this->assertCount(3, $parts);

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        $this->assertSame('RS256', $header['alg'] ?? null);
        $this->assertSame('JWT', $header['typ'] ?? null);
        $this->assertSame('kid-test', $header['kid'] ?? null);
        $this->assertSame('video-123', $payload['sub'] ?? null);
        $this->assertSame($expiresAt->getTimestamp(), $payload['exp'] ?? null);

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

        $service = new CloudflareStreamService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare signing key is missing');

        $service->createSignedPlaybackToken('video-123', new DateTimeImmutable('+30 minutes'));
    }

    public function test_fails_closed_when_signing_key_is_invalid(): void {
        $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM'] = 'invalid-key';

        $service = new CloudflareStreamService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare signing key is invalid');

        $service->createSignedPlaybackToken('video-123', new DateTimeImmutable('+30 minutes'));
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
