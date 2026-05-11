# Agent-02 — Auth Completion

**Phase**: P2 (parallel with Agent-03, Agent-04)
**Workstream**: W2 — see [`../plan/02-auth-completion.md`](../plan/02-auth-completion.md)
**PR**: PR-B
**Branch**: `agent-02-auth`

## Role

Complete the auth surface: rotate refresh tokens, add admin session login + CSRF, wire rate limits, add inactive-account lockout, drop a 2FA placeholder migration.

## Depends on

- **Agent-01 merged** (consumes `RateLimiter`, `ErrorHandler`, `Validator`, `ConfigurationException`).

## Owned files (you create)

```
backend/app/Services/SessionService.php
backend/app/Services/CsrfService.php
backend/app/Services/TwoFactorService.php
backend/app/Middleware/AdminSessionMiddleware.php
backend/app/Middleware/CsrfMiddleware.php
backend/app/Controllers/Admin/AdminAuthController.php
backend/database/migrations/003_admin_2fa.sql
backend/tests/Unit/CsrfServiceTest.php
backend/tests/Unit/AuthServiceRotationTest.php
```

## Shared files (you modify, additive only)

```
backend/app/Services/AuthService.php          — add rotating refresh + inactive lockout
backend/app/Controllers/Api/AuthController.php — return rotated token from refresh()
backend/routes/api.php                         — apply RateLimiter to /api/auth/login + /api/auth/refresh
backend/routes/admin.php                       — add /admin/login GET+POST, /admin/logout
mobile/lib/core/network/api_client.dart        — persist rotated refresh_token in _AuthInterceptor
```

## Forbidden files

`CatalogService`, `CatalogController`, catalog repositories (Agent-03). `CloudflareStreamService`, `R2Service` (Agent-04). Anything under `app/Views/` other than `auth/login.php` (a minimal stub is OK — Agent-06 styles it).

## Inputs consumed (from Agent-01)

- `RateLimiter` interface + factory.
- `Validator` for login request.
- `ErrorHandler` to surface `AuthorizationException` as 401/403 JSON.
- `Router` `{param}` support (not strictly needed for auth routes but available).

## Outputs produced

### Refresh response shape (mobile must consume rotated token)
```json
{ "access_token": "...", "refresh_token": "...", "expires_in": 900 }
```

### Admin session contract
- Session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` when `APP_ENV=production`.
- `session_regenerate_id(true)` on successful login.
- CSRF token available via `CsrfService::token()`; required on every admin POST.

### Error codes (new)
- `account_inactive` — `is_active=0` user login attempt.
- `invalid_credentials` — bad password (unchanged).
- `rate_limited` — 429 from login/refresh.
- `csrf_invalid` — 419 on admin POST without/with bad token.

## Acceptance criteria

- [ ] `POST /api/auth/refresh` returns a new refresh token; the old one fails on second use.
- [ ] Mobile interceptor persists the rotated refresh token (verify by killing access token, refreshing, then killing access again — second refresh must succeed because new refresh was saved).
- [ ] 6th rapid login from same IP → 429.
- [ ] `/admin/login` issues session cookie; subsequent `/admin/dashboard` GET works (placeholder OK — Agent-06 builds the real dashboard).
- [ ] Admin POST without CSRF → 419.
- [ ] `is_active=0` user → 403 `account_inactive`.
- [ ] Migration `003_admin_2fa.sql` applies cleanly and adds `users.totp_secret` nullable.

## Handoff note template

```
## Handoff (Agent-02)
- New env vars: SESSION_LIFETIME_MINUTES (optional, default 30)
- Migrations: 003_admin_2fa.sql
- Contracts locked:
  - /api/auth/refresh returns rotated refresh_token (Agent-08 must persist it)
  - AdminSessionMiddleware mounted on /admin/* (Agent-06 builds on this)
  - CsrfMiddleware mounted on admin POSTs (Agent-06 forms must embed csrf_field())
- Open follow-ups: TOTP verification path (not in scope)
```
