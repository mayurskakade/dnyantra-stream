<?php
namespace App\Services;

use App\Config\Config;
use App\Exceptions\ConfigurationException;
use App\Support\HttpClient;
use RuntimeException;

class CloudflareStreamService {
    private const API_BASE_URL = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private readonly HttpClient $httpClient = new HttpClient(),
    ) {}

    public function createDirectUploadUrl(array $meta): array {
        $url = self::API_BASE_URL . '/accounts/' . $this->accountId() . '/stream/direct_upload';
        $response = $this->httpClient->post(
            $url,
            $this->authHeaders(),
            json_encode($meta, JSON_UNESCAPED_SLASHES)
        );
        $result = $this->parseCloudflareResult($response, 'create direct upload URL');

        return [
            'upload_url' => (string)($result['uploadURL'] ?? $result['upload_url'] ?? ''),
            'uid' => (string)($result['uid'] ?? ''),
        ];
    }

    public function getVideoStatus(string $uid): array {
        $safeUid = trim($uid);
        if ($safeUid === '') {
            throw new RuntimeException('Video uid is required');
        }

        $url = self::API_BASE_URL . '/accounts/' . $this->accountId() . '/stream/' . rawurlencode($safeUid);
        $response = $this->httpClient->get($url, $this->authHeaders());
        return $this->parseCloudflareResult($response, 'get video status');
    }

    public function createSignedPlaybackToken(string $videoUid, int $expiresAt, array $accessRules = []): string {
        $safeVideoUid = trim($videoUid);
        if ($safeVideoUid === '') {
            throw new RuntimeException('Video uid is required');
        }

        if ($expiresAt <= 0) {
            throw new RuntimeException('Expiration timestamp must be a positive integer');
        }

        $privateKey = $this->resolveSigningKey();
        $kid = $this->resolveSigningKeyId();

        $header = ['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT'];
        $payload = ['sub' => $safeVideoUid, 'exp' => $expiresAt];
        if ($accessRules !== []) {
            $payload['accessRules'] = array_values($accessRules);
        }

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

    public function getHlsManifestUrl(string $videoUid, string $signedToken): string {
        $customerCode = trim((string)(Config::get('CLOUDFLARE_STREAM_CUSTOMER_CODE', '')));
        if ($customerCode === '') {
            throw new ConfigurationException('Missing required environment variable: CLOUDFLARE_STREAM_CUSTOMER_CODE');
        }

        return "https://customer-{$customerCode}.cloudflarestream.com/{$videoUid}/manifest/video.m3u8?token={$signedToken}";
    }

    public function deleteVideo(string $uid): void {
        $safeUid = trim($uid);
        if ($safeUid === '') {
            throw new RuntimeException('Video uid is required');
        }

        $url = self::API_BASE_URL . '/accounts/' . $this->accountId() . '/stream/' . rawurlencode($safeUid);
        $response = $this->httpClient->delete($url, $this->authHeaders());
        $this->parseCloudflareResult($response, 'delete video');
    }

    private function base64UrlEncode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function resolveSigningKey(): mixed {
        $privateKeyPem = str_replace('\\n', "\n", Config::require('CLOUDFLARE_STREAM_SIGNING_KEY_PEM'));
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new ConfigurationException('Invalid CLOUDFLARE_STREAM_SIGNING_KEY_PEM');
        }

        return $privateKey;
    }

    private function resolveSigningKeyId(): string {
        $kid = trim((string)(Config::get('CLOUDFLARE_STREAM_SIGNING_KEY_ID') ?? Config::get('CLOUDFLARE_STREAM_KEY_ID', '')));
        if ($kid === '') {
            throw new ConfigurationException('Missing required environment variable: CLOUDFLARE_STREAM_SIGNING_KEY_ID');
        }

        return $kid;
    }

    private function accountId(): string {
        return trim(Config::require('CLOUDFLARE_ACCOUNT_ID'));
    }

    private function authHeaders(): array {
        return [
            'Authorization' => 'Bearer ' . trim(Config::require('CLOUDFLARE_STREAM_API_TOKEN')),
            'Content-Type' => 'application/json',
        ];
    }

    private function parseCloudflareResult(array $response, string $action): array {
        $status = (int)($response['status'] ?? 0);
        $decoded = json_decode((string)($response['body'] ?? ''), true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Cloudflare API failed to {$action}: invalid JSON response");
        }

        $success = (bool)($decoded['success'] ?? false);
        if ($status < 200 || $status >= 300 || !$success) {
            $errors = $decoded['errors'] ?? [];
            $message = "Cloudflare API failed to {$action}";
            if (is_array($errors) && isset($errors[0]['message'])) {
                $message .= ': ' . (string)$errors[0]['message'];
            }
            throw new RuntimeException($message);
        }

        $result = $decoded['result'] ?? [];
        if (!is_array($result)) {
            throw new RuntimeException("Cloudflare API failed to {$action}: missing result payload");
        }

        return $result;
    }
}
