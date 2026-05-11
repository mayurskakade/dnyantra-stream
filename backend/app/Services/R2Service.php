<?php
namespace App\Services;

use App\Config\Config;
use App\Exceptions\ValidationException;
use App\Support\HttpClient;
use RuntimeException;

class R2Service {
    private const REGION = 'auto';
    private const SERVICE = 's3';
    private const MAX_UPLOAD_TTL = 900;
    private const MAX_READ_TTL = 3600;

    private const ALLOWED_PREFIXES = [
        'posters/',
        'banners/',
        'subtitles/',
    ];

    private const ALLOWED_CONTENT_TYPES = [
        'posters/' => ['image/jpeg', 'image/png', 'image/webp'],
        'banners/' => ['image/jpeg', 'image/png', 'image/webp'],
        'subtitles/' => ['text/vtt', 'application/x-subrip'],
    ];

    public function __construct(
        private readonly HttpClient $httpClient = new HttpClient(),
    ) {}

    public function createPresignedUploadUrl(string $key, string $contentType, int $ttlSeconds = self::MAX_UPLOAD_TTL): string {
        $safeKey = $this->validateObjectKey($key);
        $prefix = $this->resolvePrefix($safeKey);
        $this->validateContentType($prefix, $contentType);
        $ttl = $this->normalizeTtl($ttlSeconds, self::MAX_UPLOAD_TTL);

        return $this->buildPresignedUrl('PUT', $safeKey, $ttl);
    }

    public function createPresignedReadUrl(string $key, int $ttlSeconds = self::MAX_READ_TTL): string {
        $safeKey = $this->validateObjectKey($key);
        $ttl = $this->normalizeTtl($ttlSeconds, self::MAX_READ_TTL);

        return $this->buildPresignedUrl('GET', $safeKey, $ttl);
    }

    public function deleteObject(string $key): void {
        $safeKey = $this->validateObjectKey($key);

        $host = $this->host();
        $bucket = $this->bucket();
        $uri = '/' . rawurlencode($bucket) . '/' . $this->encodeObjectKey($safeKey);
        $url = 'https://' . $host . $uri;

        $now = gmdate('Ymd\THis\Z');
        $shortDate = substr($now, 0, 8);
        $payloadHash = hash('sha256', '');

        $canonicalRequest = "DELETE\n{$uri}\n\nhost:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$now}\n\nhost;x-amz-content-sha256;x-amz-date\n{$payloadHash}";
        $scope = $shortDate . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$scope}\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($shortDate));

        $auth = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKeyId() . '/' . $scope
            . ', SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature=' . $signature;

        $response = $this->httpClient->delete($url, [
            'Authorization' => $auth,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ]);

        $status = (int)($response['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('R2 delete failed with status ' . $status);
        }
    }

    private function buildPresignedUrl(string $method, string $key, int $ttl): string {
        $host = $this->host();
        $bucket = $this->bucket();
        $credentialKey = $this->accessKeyId();

        $amzDate = gmdate('Ymd\THis\Z');
        $shortDate = substr($amzDate, 0, 8);
        $scope = $shortDate . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request';
        $canonicalUri = '/' . rawurlencode($bucket) . '/' . $this->encodeObjectKey($key);

        $queryParams = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $credentialKey . '/' . $scope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string)$ttl,
            'X-Amz-SignedHeaders' => 'host',
        ];

        $canonicalQuery = $this->buildCanonicalQuery($queryParams);
        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQuery}\nhost:{$host}\n\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($shortDate));

        $queryParams['X-Amz-Signature'] = $signature;
        $finalQuery = $this->buildCanonicalQuery($queryParams);

        return 'https://' . $host . $canonicalUri . '?' . $finalQuery;
    }

    private function validateObjectKey(string $key): string {
        $safeKey = trim($key);
        if ($safeKey === '') {
            throw new ValidationException('Object key is required');
        }

        if (str_contains($safeKey, '..') || str_starts_with($safeKey, '/')) {
            throw new ValidationException('Object key contains forbidden path traversal segments');
        }

        if ($this->resolvePrefix($safeKey) === null) {
            throw new ValidationException('Object key prefix is not allowed');
        }

        return $safeKey;
    }

    private function resolvePrefix(string $key): ?string {
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    private function validateContentType(string $prefix, string $contentType): void {
        $allowed = self::ALLOWED_CONTENT_TYPES[$prefix] ?? [];
        if (!in_array($contentType, $allowed, true)) {
            throw new ValidationException('Content type is not allowed for object prefix');
        }
    }

    private function normalizeTtl(int $ttlSeconds, int $maxSeconds): int {
        if ($ttlSeconds <= 0) {
            throw new ValidationException('TTL must be greater than zero');
        }

        return min($ttlSeconds, $maxSeconds);
    }

    private function buildCanonicalQuery(array $queryParams): string {
        ksort($queryParams);
        $parts = [];
        foreach ($queryParams as $key => $value) {
            $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }

        return implode('&', $parts);
    }

    private function signingKey(string $shortDate): string {
        $kDate = hash_hmac('sha256', $shortDate, 'AWS4' . $this->secretAccessKey(), true);
        $kRegion = hash_hmac('sha256', self::REGION, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function encodeObjectKey(string $key): string {
        $segments = explode('/', $key);
        $encoded = array_map(static fn(string $segment): string => rawurlencode($segment), $segments);
        return implode('/', $encoded);
    }

    private function host(): string {
        return $this->accountId() . '.r2.cloudflarestorage.com';
    }

    private function accountId(): string {
        return trim(Config::require('R2_ACCOUNT_ID'));
    }

    private function accessKeyId(): string {
        return trim(Config::require('R2_ACCESS_KEY_ID'));
    }

    private function secretAccessKey(): string {
        return trim(Config::require('R2_SECRET_ACCESS_KEY'));
    }

    private function bucket(): string {
        return trim(Config::require('R2_BUCKET'));
    }
}
