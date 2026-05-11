# Agent-04 — Cloudflare Stream + R2

**Phase**: P2 (parallel with Agent-02, Agent-03)
**Workstream**: W5 — see [`../plan/04-cloudflare-integration.md`](../plan/04-cloudflare-integration.md)
**PR**: PR-D
**Branch**: `agent-04-cloudflare`

## Role

Implement real Cloudflare Stream JWT signing (RS256), direct-upload URL creation, status sync, deletion, and R2 SigV4 presigning with key + content-type whitelists. Provide thin HTTP client.

## Depends on

- **Agent-01 merged** (consumes `ConfigurationException`, `ValidationException`, `ErrorHandler`).

## Owned files (you create)

```
backend/app/Support/HttpClient.php             (only if no guzzle in composer.lock; check first)
backend/tests/Unit/CloudflareStreamServiceTest.php
backend/tests/Unit/R2ServiceTest.php
```

## Shared files (you modify, additive only)

```
backend/app/Services/CloudflareStreamService.php  — implement (currently empty stub)
backend/app/Services/R2Service.php                — implement (currently empty stub)
```

## Forbidden files

Anything under `app/Controllers/`, `app/Repositories/`, `app/Middleware/`, `routes/`. You produce primitives; other agents call them.

## Inputs consumed (from Agent-01)

- `ConfigurationException` thrown when env vars missing/invalid.
- `Config::require()` for env reads.

## Outputs produced

### CloudflareStreamService (Agent-05 + Agent-06 consume)
```php
public function createSignedPlaybackToken(string $videoUid, int $expiresAt, array $accessRules = []): string;
// returns RS256 JWT; header has {alg, kid, typ}; payload has {sub: $videoUid, exp: $expiresAt}

public function createDirectUploadUrl(array $meta): array;
// returns {upload_url: string, uid: string}

public function getVideoStatus(string $uid): array;
public function deleteVideo(string $uid): void;
```

### R2Service (Agent-06 consumes)
```php
public function createPresignedUploadUrl(string $key, string $contentType, int $ttlSeconds = 900): string;
public function createPresignedReadUrl(string $key, int $ttlSeconds = 3600): string;
public function deleteObject(string $key): void;
```

### Whitelists (enforced internally — throw `ValidationException` on violation)
- Key prefixes allowed: `posters/`, `banners/`, `subtitles/`.
- Forbidden in key: `..`, leading `/`, anything outside whitelist.
- Content-type per prefix:
  - `posters/`, `banners/`: `image/jpeg`, `image/png`, `image/webp`.
  - `subtitles/`: `text/vtt`, `application/x-subrip`.

### Fail-closed contract
- Missing `CLOUDFLARE_STREAM_SIGNING_KEY_PEM` → `ConfigurationException` (and Agent-01 boot validation catches it before requests are served).
- Missing `CLOUDFLARE_STREAM_API_TOKEN` → `ConfigurationException` at first call.
- Never return an unsigned token, ever.

## Acceptance criteria

- [ ] Generated JWT verifies against a test public key in `CloudflareStreamServiceTest`.
- [ ] Header is exactly `{"alg":"RS256","kid":"<env-kid>","typ":"JWT"}`; payload contains `sub`, `exp`.
- [ ] `createDirectUploadUrl` posts to the correct endpoint with `Authorization: Bearer <token>` (mock HTTP in test).
- [ ] R2 presigned URL contains `X-Amz-Signature`, `X-Amz-Expires=900`, `X-Amz-Date`.
- [ ] R2 rejects `../etc/passwd`, `/posters/x.jpg` (leading slash), `random/x.jpg` (outside whitelist).
- [ ] R2 rejects `posters/x.jpg` with content-type `application/octet-stream`.
- [ ] Missing PEM → ConfigurationException, no unsigned output.

## Handoff note template

```
## Handoff (Agent-04)
- Env vars required (Agent-01 should already validate at boot):
    CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_STREAM_API_TOKEN,
    CLOUDFLARE_STREAM_SIGNING_KEY_PEM, CLOUDFLARE_STREAM_SIGNING_KEY_ID,
    R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET
- Contracts locked: see method signatures in agents/agent-04-cloudflare.md
- Open follow-ups: webhook handler for upload status (deferred — Agent-06 uses sync endpoint)
```
