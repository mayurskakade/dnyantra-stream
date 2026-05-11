<?php
namespace App\Services;

use DateTimeImmutable;
use RuntimeException;

class CloudflareStreamService {
    public function createDirectUploadUrl(array $meta): array { return ['upload_url'=>'TODO_SECURE_BACKEND_CALL','uid'=>'pending']; }
    public function getVideoStatus(string $uid): array { return ['uid'=>$uid,'status'=>'processing']; }

    public function createSignedPlaybackToken(string $videoUid, DateTimeImmutable $expiresAt): string {
        $privateKeyPem = trim((string)($_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_PEM'] ?? ''));
        if ($privateKeyPem === '') {
            throw new RuntimeException('Cloudflare signing key is missing');
        }

        $privateKeyPem = str_replace('\\n', "\n", $privateKeyPem);
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('Cloudflare signing key is invalid');
        }

        $kid = trim((string)(
            $_ENV['CLOUDFLARE_STREAM_KEY_ID']
            ?? $_ENV['CLOUDFLARE_STREAM_SIGNING_KEY_ID']
            ?? ''
        ));

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        if ($kid !== '') {
            $header['kid'] = $kid;
        }

        $payload = [
            'sub' => $videoUid,
            'exp' => $expiresAt->getTimestamp(),
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signingInput = $encodedHeader . '.' . $encodedPayload;

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if ($signed !== true || $signature === '') {
            throw new RuntimeException('Failed to sign Cloudflare playback token');
        }

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    public function getHlsManifestUrl(string $videoUid, string $signedToken): string { return "https://customer-".$_ENV['CLOUDFLARE_STREAM_CUSTOMER_CODE'].".cloudflarestream.com/$videoUid/manifest/video.m3u8?token=$signedToken"; }
    public function deleteVideo(string $uid): bool { return true; }

    private function base64UrlEncode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
