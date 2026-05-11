# 04 — Cloudflare Integration: Stream + R2 (W5)

**Blocks**: W6 (playback sessions need signed Stream tokens), W7 (admin media uploads).
**Depends on**: W1 (error handler so failures surface as structured JSON).

## Why

`CloudflareStreamService` and `R2Service` are currently empty stubs. Without real signing and presigning, no playback URL can be issued and no admin upload can be initiated.

## Deliverables

### 1. Cloudflare Stream — signed playback tokens (RS256)

- `backend/app/Services/CloudflareStreamService.php::createSignedPlaybackToken(string $videoUid, int $expiresAt, array $accessRules = []): string`
  - JWT (RS256) per CF Stream docs.
  - Header: `{alg: "RS256", kid: <CLOUDFLARE_STREAM_SIGNING_KEY_ID>, typ: "JWT"}`.
  - Payload: `{sub: $videoUid, exp: $expiresAt, kid: ..., accessRules?: [...]}`.
  - Sign via `openssl_sign()` with `CLOUDFLARE_STREAM_SIGNING_KEY_PEM`. No library needed.
  - Fail closed: if PEM missing/invalid → throw `ConfigurationException`; never return an unsigned token.

### 2. Cloudflare Stream — direct upload URL

- `createDirectUploadUrl(array $meta): array`
  - `POST https://api.cloudflare.com/client/v4/accounts/{accountId}/stream/direct_upload`
  - Auth: `Authorization: Bearer CLOUDFLARE_STREAM_API_TOKEN`.
  - Returns `{upload_url, uid}`.
- `getVideoStatus(string $uid): array` — GET, returns status/duration/playback info.
- `deleteVideo(string $uid): void` — DELETE.

### 3. R2 — SigV4 presigning

- `backend/app/Services/R2Service.php`:
  - `createPresignedUploadUrl(string $key, string $contentType, int $ttlSeconds = 900): string`
  - `createPresignedReadUrl(string $key, int $ttlSeconds = 3600): string`
  - `deleteObject(string $key): void`
- SigV4 query-param signing against `https://{R2_ACCOUNT_ID}.r2.cloudflarestorage.com/{R2_BUCKET}/{key}`.
- **Key prefix whitelist**: only `posters/`, `banners/`, `subtitles/`. Reject `..`, leading `/`, anything outside whitelist.
- **Content-type whitelist** per prefix:
  - `posters/`, `banners/`: `image/jpeg`, `image/png`, `image/webp`
  - `subtitles/`: `text/vtt`, `application/x-subrip`
- Short TTLs: upload ≤ 15 min, read ≤ 60 min.

### 4. HTTP client abstraction

- New `backend/app/Support/HttpClient.php` — thin curl wrapper with `get()`, `post()`, `delete()`, JSON helpers, timeout, error mapping.
- Or use existing `composer require guzzlehttp/guzzle` if already pulled. Decide based on `composer.lock`.

### 5. Tests

- `backend/tests/Unit/CloudflareStreamServiceTest.php`:
  - JWT header has `alg=RS256` and a `kid`.
  - Payload contains `sub`, `exp` matching inputs.
  - Signature verifies against a test public key.
  - Throws on missing PEM, missing API token.
- `backend/tests/Unit/R2ServiceTest.php`:
  - Canonical request shape per AWS spec.
  - Rejects keys with `..` / leading `/` / outside whitelist.
  - Rejects content-type outside whitelist per prefix.
  - Expired-URL clock skew handling.

## Files

**Create**: `app/Support/HttpClient.php` (if needed), `app/Exceptions/ConfigurationException.php`, `tests/Unit/CloudflareStreamServiceTest.php`, `tests/Unit/R2ServiceTest.php`.

**Modify**: `app/Services/CloudflareStreamService.php`, `app/Services/R2Service.php`.

## Acceptance

- `CloudflareStreamService::createSignedPlaybackToken('test-uid', time()+600)` returns a 3-segment RS256 JWT, signature verifies against the configured public key.
- `R2Service::createPresignedUploadUrl('posters/abc.jpg', 'image/jpeg')` returns a URL containing `X-Amz-Signature`, `X-Amz-Expires=900`, `X-Amz-Date`.
- `R2Service::createPresignedUploadUrl('../etc/passwd', 'image/jpeg')` throws `ValidationException`.
- `R2Service::createPresignedUploadUrl('posters/x.jpg', 'application/octet-stream')` throws `ValidationException`.
- Missing `CLOUDFLARE_STREAM_SIGNING_KEY_PEM` → `ConfigurationException` at first sign attempt (or earlier at boot — see W1 boot validation).

## Out of scope

- Webhook handling from Cloudflare for upload status — admin uses sync endpoint instead.
- DRM, watermarking, content protection beyond signed playback URLs.
