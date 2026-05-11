# 02 — Auth Completion (W2)

**Blocks**: W7 (admin portal session login), W8 (security sweep needs rotation in place).
**Depends on**: W1 (rate limiter + error handler).

## Why

API auth already works for login/refresh/logout/me, but refresh does **not rotate** the token, there is **no admin session login** (only an API JWT middleware), and login is **not rate-limited**. All three are required before opening up admin and public playback paths.

## Deliverables

### 1. Refresh-token rotation

- `backend/app/Services/AuthService.php::refresh($refreshToken)`:
  1. Verify hash + not-revoked + not-expired in a transaction.
  2. Revoke the supplied refresh token (`revoked_at = NOW()`).
  3. Insert a new refresh token row (hashed) with fresh expiry.
  4. Return `{access_token, refresh_token, expires_in}` (new shape).
- `backend/app/Controllers/Api/AuthController.php::refresh()`: return the rotated refresh token in the response body.
- `mobile/lib/core/network/api_client.dart` `_AuthInterceptor`: on successful refresh, **persist the new refresh token** in secure storage. Currently only access token is rotated client-side.

### 2. Admin session + CSRF

- New `backend/app/Services/SessionService.php`:
  - `session_start()` with `cookie_httponly=1`, `cookie_secure=1` (env-gated; off in local HTTP dev), `cookie_samesite=Lax`.
  - Regenerate id on login (`session_regenerate_id(true)`).
  - Idle timeout (e.g. 30 min) tracked in session payload.
- New `backend/app/Services/CsrfService.php`:
  - Per-session token, lazy-generated, reused across forms in the session.
  - `validate(string $submitted): bool` uses `hash_equals`.
- New `backend/app/Middleware/AdminSessionMiddleware.php`: replaces `AuthMiddleware` for `/admin/*` (except `/admin/login` GET/POST).
- New `backend/app/Middleware/CsrfMiddleware.php`: enforced on all admin POSTs. Failure → `419` HTML response.
- New `backend/app/Controllers/Admin/AdminAuthController.php`: `showLogin`, `login`, `logout`. Login renders the form view (created in W7 but stub OK here).

### 3. Login / refresh rate limiting

- Wire `RateLimiter` (from W1) into:
  - `POST /api/auth/login` — e.g. `5 / 60s / IP+email`.
  - `POST /api/auth/refresh` — e.g. `30 / 60s / IP`.
  - `POST /admin/login` — e.g. `5 / 60s / IP+email`.
- On exceed: `429` with `Retry-After` header.

### 4. Inactive account lockout

- `AuthService::login()`: when `users.is_active = 0`, return distinct error `account_inactive` (not generic `invalid_credentials`). Mobile shows a tailored message.

### 5. Optional 2FA placeholder

- New migration `backend/database/migrations/003_admin_2fa.sql`: add `users.totp_secret VARCHAR(64) NULL`.
- New `backend/app/Services/TwoFactorService.php` — stub interface only. No enforcement path yet.

## Files

**Create**: `app/Services/SessionService.php`, `app/Services/CsrfService.php`, `app/Services/TwoFactorService.php`, `app/Middleware/AdminSessionMiddleware.php`, `app/Middleware/CsrfMiddleware.php`, `app/Controllers/Admin/AdminAuthController.php`, `database/migrations/003_admin_2fa.sql`.

**Modify**: `app/Services/AuthService.php`, `app/Controllers/Api/AuthController.php`, `mobile/lib/core/network/api_client.dart`, `routes/api.php`, `routes/admin.php`.

## Acceptance

- `POST /api/auth/refresh` returns a new refresh token; old token is rejected on a second call.
- Mobile: kill access token mid-session → interceptor refreshes → next request succeeds → secure storage now holds the rotated refresh token.
- 6 rapid `POST /api/auth/login` attempts from the same IP → `429`.
- Admin login at `/admin/login` issues a session cookie (`HttpOnly`, `SameSite=Lax`); subsequent admin GETs succeed; admin POST without CSRF token → `419`.
- Login with `is_active=0` user → `403` with `error.code = "account_inactive"`.

## Out of scope

- TOTP verification, recovery codes, enrollment UI — schema + stub only.
- Per-user session limits.
