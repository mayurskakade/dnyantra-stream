# 08 — Security Hardening Sweep (W8)

**Blocks**: M6 (release ready).
**Depends on**: W1–W7 landed.

## Why

Spec §14 is a hard gate on release. This is a final pass that verifies every prior workstream upholds the security contract and adds the cross-cutting pieces (log redaction, secret scanning) that have no natural home in feature workstreams.

## Deliverables

### 1. SQL injection audit

- Grep every PHP source file for `->query(`, `->exec(`. For each hit, confirm no user input is concatenated.
- Confirm all repositories use `prepare()` + `execute()` exclusively.
- Add to CI a grep guard that fails if PR diff introduces `->query(` outside test fixtures.

### 2. Password hashing audit

- Confirm `password_hash($pwd, PASSWORD_DEFAULT)` is the only place hashes are produced.
- Confirm `password_verify($pwd, $hash)` is the only check path. No custom comparison.

### 3. CSRF coverage audit

- Every route under `/admin/*` POST/PUT/PATCH/DELETE goes through `CsrfMiddleware`.
- Add a test that asserts CSRF rejection for a sampling of admin POST routes.

### 4. Input validation coverage

- Every controller action that reads request body / query params runs values through `Validator`.
- Whitelist enums enforced: `visibility`, `rights_status`, `status`, `role`.
- Add unit tests for `Validator` enum rejection.

### 5. Token hashing audit

Confirm hashed-storage + constant-time comparison on read for:

- `refresh_tokens.token_hash`
- `share_links.token_hash`
- `playback_sessions.session_token_hash`

Use `hash_equals` everywhere.

### 6. Rate limit coverage

Confirm `RateLimiter` is wired to:

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /admin/login`
- `POST /public/playback-sessions`
- (Optional) `GET /public/catalog`

### 7. Log redaction

- New `backend/app/Support/LogRedactor.php`. Scrubs the following keys (case-insensitive) from any associative array logged: `password`, `password_confirmation`, `authorization`, `cookie`, `access_token`, `refresh_token`, `token`, `token_hash`, `session_token`, `csrf`.
- Hook into the structured logger used by `ErrorHandler` and any explicit `error_log` JSON outputs.

### 8. Secrets / .env

- `.env.example` audit — only placeholders, no real keys.
- CI job: `git ls-files | grep -E '\.env$'` returns nothing; fail if a real `.env` is committed.
- CI job: grep PR diff for common secret patterns (AKIA, BEGIN PRIVATE KEY, eyJ...long JWT prefixes outside test fixtures) and warn.

### 9. Public response leak audit

- Walk every public + auth catalog endpoint and assert the response shape contains **none** of:
  - `provider_uid`
  - raw `storage_key`
  - `rights_status`
  - `public_streaming_enabled`
  - any signing token / hash
- Codify with a `tests/Feature/ResponseShapeTest.php` that hits each endpoint and asserts the keys are absent.

### 10. Admin guard verification

- Tests for `MoviesController::update`:
  - `visibility=public` + `rights_status=personal_only` → 422.
  - `visibility=public` + `rights_status=owned_by_me` + `public_streaming_enabled=false` → 422.
  - All-cleared → 200 + audit log row written.

## Files

**Create**: `app/Support/LogRedactor.php`, `tests/Feature/ResponseShapeTest.php`, `tests/Feature/AdminGuardsTest.php`, `tests/Feature/CsrfCoverageTest.php`. CI additions go in W11.

**Modify**: `app/Core/ErrorHandler.php` (use `LogRedactor`), any logger call sites.

## Acceptance

- All §14 checklist items demonstrably satisfied by tests or grep guards in CI.
- Sample admin write produces an `admin_audit_logs` row with non-null `before` / `after` JSON.
- A deliberate PR that adds a real-looking secret triggers the CI secret-scan job.
- Public endpoint response bodies contain none of the leak-sensitive keys.

## Out of scope

- Penetration testing / external audit — separate effort.
- WAF / IP allowlisting / DDoS protection — infrastructure layer.
