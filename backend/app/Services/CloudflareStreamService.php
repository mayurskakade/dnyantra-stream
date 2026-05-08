<?php
namespace App\Services;
use DateTimeImmutable;
use RuntimeException;

class CloudflareStreamService {
    public function createDirectUploadUrl(array $meta): array { return ['upload_url'=>'TODO_SECURE_BACKEND_CALL','uid'=>'pending']; }
    public function getVideoStatus(string $uid): array { return ['uid'=>$uid,'status'=>'processing']; }
    public function createSignedPlaybackToken(string $videoUid, DateTimeImmutable $expiresAt): string {
        throw new RuntimeException('TODO: implement Cloudflare Stream JWT signing with CLOUDFLARE_STREAM_SIGNING_KEY_PEM. Fail closed.');
    }
    public function getHlsManifestUrl(string $videoUid, string $signedToken): string { return "https://customer-".$_ENV['CLOUDFLARE_STREAM_CUSTOMER_CODE'].".cloudflarestream.com/$videoUid/manifest/video.m3u8?token=$signedToken"; }
    public function deleteVideo(string $uid): bool { return true; }
}
