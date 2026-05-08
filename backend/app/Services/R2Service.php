<?php
namespace App\Services;
use RuntimeException;
class R2Service {
    public function createPresignedUploadUrl(string $key,string $contentType,int $expiresSeconds): string { throw new RuntimeException('TODO: implement AWS SigV4 for R2 presigned upload.'); }
    public function createPresignedReadUrl(string $key,int $expiresSeconds): string { throw new RuntimeException('TODO: implement AWS SigV4 for R2 presigned read.'); }
    public function deleteObject(string $key): bool { return true; }
}
