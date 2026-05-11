# 01 — Backend Core Hardening (W1)

**Blocks**: every endpoint shipped after this (W2–W8).
**Why first**: uniform error JSON, validated env at boot, route params for slug routes, CORS for the Flutter origin, and a rate-limiter abstraction are all preconditions for the catalog, playback, and admin work.

## Deliverables

### 1. Route params

- `backend/app/Core/Router.php`: extend `add()` to accept `{param}` segments. Match against requests and populate `Request::setAttribute('route.params', [...])`. Current router only does exact-string match — without this, `/api/movies/{slug}` is impossible.
- `backend/app/Core/Request.php`: add `getRouteParam(string $name): ?string`.

### 2. Global error handler

- New `backend/app/Core/ErrorHandler.php`. Installed in `public/index.php` and `public/admin.php` via `set_exception_handler` + `set_error_handler`.
  - API path (`/api/*`, `/public/*`): structured JSON `{error: {code, message, details?}}` with appropriate HTTP status.
  - Admin path (`/admin/*`): HTML error view.
  - Recognizes: `ValidationException`, `AuthorizationException`, `NotFoundException`, generic `Throwable` (500, log only).

### 3. Validator

- New `backend/app/Core/Validator.php`. Rule set covers `required`, `string`, `int`, `bool`, `email`, `enum`, `min`, `max`, `regex`, `uuid`. Whitelist-enum aware for `visibility`, `rights_status`, `status`, `role`. Throws `ValidationException` with `details` shaped for the error handler.

### 4. Config boot validation

- `backend/app/Config/Config.php`: add `require(string $key): string` (throws on missing) and `bootValidate(array $keys): void`.
- Call `Config::bootValidate([...])` at the top of `public/index.php` and `public/admin.php` for: `DB_HOST`, `JWT_SECRET`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_STREAM_API_TOKEN`, `CLOUDFLARE_STREAM_SIGNING_KEY_PEM`, `R2_ACCOUNT_ID`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `APP_URL`, `API_ALLOWED_ORIGINS`.

### 5. CORS middleware

- New `backend/app/Middleware/CorsMiddleware.php`.
  - Origin allow-list from `API_ALLOWED_ORIGINS` (comma-separated).
  - Allowed methods: `GET, POST, PUT, PATCH, DELETE, OPTIONS`.
  - Allowed headers: `Authorization, Content-Type, X-CSRF-Token`.
  - Preflight (`OPTIONS`) short-circuits with `204`.
  - Applied globally to `/api/*` and `/public/*`.

### 6. Rate limiter abstraction

- New `backend/app/Services/RateLimiter.php` — interface `RateLimiter { public function hit(string $key, int $limit, int $windowSeconds): RateLimitResult; }`.
- `RedisRateLimiter` (atomic INCR + EXPIRE).
- `InMemoryRateLimiter` (apcu or static array; for local single-process dev).
- Factory selects backend: Redis when `REDIS_HOST` set, in-memory otherwise.
- Will be consumed by W2 (login/refresh) and W6 (public playback sessions).

### 7. Container (optional, light)

- Only add `backend/app/Core/Container.php` if middleware composition gets ugly resolving `RateLimiter` / `AuthService` without globals. Otherwise stay with static factories. Decide during implementation.

## Files

**Create**: `app/Core/ErrorHandler.php`, `app/Core/Validator.php`, `app/Middleware/CorsMiddleware.php`, `app/Services/RateLimiter.php` (+ `RedisRateLimiter.php`, `InMemoryRateLimiter.php`), `app/Exceptions/{ValidationException,AuthorizationException,NotFoundException}.php`.

**Modify**: `app/Core/Router.php`, `app/Core/Request.php`, `app/Config/Config.php`, `public/index.php`, `public/admin.php`.

## Acceptance

- `GET /api/me` from a disallowed origin returns CORS-blocked response.
- `POST /api/auth/login` with malformed JSON returns `400` with `{error:{code:"validation_failed",...}}`.
- Missing `JWT_SECRET` env aborts boot with a fatal error before any request handling.
- Route `/api/movies/{slug}` resolves and `Request::getRouteParam('slug')` returns the value.
- Hitting `POST /api/auth/login` 6 times in 1 minute from the same IP returns `429` (driven by `RateLimiter`, even though the wire-up of the limiter to login is W2).

## Out of scope

- Per-route validation rule registries (controllers call `Validator` directly).
- Anything observability-related beyond structured error logging.

## Depends on

Nothing — this is the foundation.
