<?php
namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Services\R2Service;
use PHPUnit\Framework\TestCase;

class R2ServiceTest extends TestCase {
    private array $envBackup = [];

    protected function setUp(): void {
        $this->envBackup = [
            'R2_ACCOUNT_ID' => $_ENV['R2_ACCOUNT_ID'] ?? null,
            'R2_ACCESS_KEY_ID' => $_ENV['R2_ACCESS_KEY_ID'] ?? null,
            'R2_SECRET_ACCESS_KEY' => $_ENV['R2_SECRET_ACCESS_KEY'] ?? null,
            'R2_BUCKET' => $_ENV['R2_BUCKET'] ?? null,
        ];

        $_ENV['R2_ACCOUNT_ID'] = 'account123';
        $_ENV['R2_ACCESS_KEY_ID'] = 'AKIDEXAMPLE';
        $_ENV['R2_SECRET_ACCESS_KEY'] = 'secret123';
        $_ENV['R2_BUCKET'] = 'media-bucket';
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

    public function test_presigned_upload_url_contains_sigv4_query_parts(): void {
        $service = new R2Service();
        $url = $service->createPresignedUploadUrl('posters/abc.jpg', 'image/jpeg');

        $this->assertStringContainsString('X-Amz-Signature=', $url);
        $this->assertStringContainsString('X-Amz-Expires=900', $url);
        $this->assertStringContainsString('X-Amz-Date=', $url);
    }

    public function test_rejects_forbidden_keys(): void {
        $service = new R2Service();

        $this->expectException(ValidationException::class);
        $service->createPresignedUploadUrl('../etc/passwd', 'image/jpeg');
    }

    public function test_rejects_leading_slash_key(): void {
        $service = new R2Service();

        $this->expectException(ValidationException::class);
        $service->createPresignedUploadUrl('/posters/x.jpg', 'image/jpeg');
    }

    public function test_rejects_key_outside_whitelist(): void {
        $service = new R2Service();

        $this->expectException(ValidationException::class);
        $service->createPresignedUploadUrl('random/x.jpg', 'image/jpeg');
    }

    public function test_rejects_invalid_content_type_for_prefix(): void {
        $service = new R2Service();

        $this->expectException(ValidationException::class);
        $service->createPresignedUploadUrl('posters/x.jpg', 'application/octet-stream');
    }
}
