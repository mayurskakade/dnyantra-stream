# Agent-01 — Core Hardening

**Phase**: P1 (sequential — blocks everything)
**Workstream**: W1 — see [`../plan/01-backend-core-hardening.md`](../plan/01-backend-core-hardening.md)
**PR**: PR-A
**Branch**: `agent-01-core-hardening`

## Role

Ship the foundation that every later agent depends on: parametric routing, structured errors, validator, CORS, rate-limiter abstraction, and env boot validation. You are the only agent in Phase 1 — no parallel coordination needed.

## Depends on

Nothing. Base off `main`.

## Owned files (you create)

```
backend/app/Core/ErrorHandler.php
backend/app/Core/Validator.php
backend/app/Middleware/CorsMiddleware.php
backend/app/Services/RateLimiter.php                  (interface)
backend/app/Services/RedisRateLimiter.php
backend/app/Services/InMemoryRateLimiter.php
backend/app/Exceptions/ValidationException.php
backend/app/Exceptions/AuthorizationException.php
backend/app/Exceptions/NotFoundException.php
backend/app/Exceptions/ConfigurationException.php
```

## Shared files (you modify, additive only)

```
backend/app/Core/Router.php       — add {param} segment matching
backend/app/Core/Request.php      — add getRouteParam(string $name): ?string
backend/app/Config/Config.php     — add require() + bootValidate()
backend/public/index.php          — install error handler + boot validate + mount CORS
backend/public/admin.php          — install error handler + boot validate
```

## Forbidden files

Anything under `backend/app/Controllers/`, `backend/app/Repositories/`, `backend/app/Services/Auth*`, `backend/app/Services/CloudflareStreamService.php`, `backend/app/Services/R2Service.php`, `backend/app/Services/PlaybackService.php`, `backend/app/Services/WatchProgressService.php`. Those belong to later agents.

## Inputs consumed

None — foundation layer.

## Outputs produced (contracts for downstream agents)

### Route param API
```php
$router->add('GET', '/api/movies/{slug}', [CatalogController::class, 'showMovie']);
$slug = $request->getRouteParam('slug'); // returns string|null
```

### Error JSON shape (Agent-02, 03, 05 must conform)
```json
{ "error": { "code": "validation_failed", "message": "...", "details": { "field": ["..."] } } }
```

### RateLimiter interface
```php
interface RateLimiter {
    public function hit(string $key, int $limit, int $windowSeconds): RateLimitResult;
}
final class RateLimitResult {
    public bool $allowed;
    public int $remaining;
    public int $retryAfterSeconds; // 0 if allowed
}
```

### Validator usage
```php
$data = $validator->validate($request->all(), [
    'visibility' => 'required|enum:private,authenticated,unlisted,public',
    'page' => 'int|min:1',
]);
```

### ConfigurationException
Thrown at boot when a required env var is missing; surfaced as JSON 500 by ErrorHandler in dev.

## Acceptance criteria

- [ ] Smoke route `GET /api/movies/{slug}` resolves and `Request::getRouteParam('slug')` returns the value.
- [ ] Bad JSON body → 400 with structured error JSON.
- [ ] CORS preflight from disallowed origin → blocked; allowed origin → 204 with correct headers.
- [ ] Missing `JWT_SECRET` → boot aborts before request handling.
- [ ] `RateLimiter::hit()` under Redis backend correctly increments and expires; under InMemory backend works for the same test cases (single process).
- [ ] Unit tests for `Validator` (each rule + enum), `RateLimiter` (both backends), `ErrorHandler` (each exception type).

## Handoff note template

```
## Handoff (Agent-01)
- New env vars required: API_ALLOWED_ORIGINS
- Contracts locked:
  - Error JSON shape: {error:{code,message,details?}}
  - RateLimiter interface (see agents/agent-01-core-hardening.md)
  - Route param API: Request::getRouteParam(name)
- Open follow-ups: none
```
