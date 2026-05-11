# 10 — PR Sequence, Risks, and E2E Verification

## PR sequence

Each PR is independently mergeable and gated on CI (W11 wires the workflow in PR-I, but earlier PRs can run tests locally).

| PR | Scope | Workstreams | Depends on |
|---|---|---|---|
| **PR-A** | Route params, ErrorHandler, Validator, `Config::require` boot validation, `CorsMiddleware`, `RateLimiter` abstraction (Redis + in-memory). | W1 | — |
| **PR-B** | Refresh-token rotation (+ mobile interceptor update), admin session login + CSRF, login/refresh rate limiting, inactive-account lockout, 2FA placeholder migration. | W2 | PR-A |
| **PR-C** | Catalog repositories, transformers, `CatalogService`, `/api/*` and `/public/*` read endpoints, access-policy enforcement at every endpoint. | W4 | PR-A |
| **PR-D** | Cloudflare Stream JWT signing + R2 SigV4 + their tests. | W5 | PR-A |
| **PR-E** | Playback sessions (auth + public), watch-progress DB persistence + endpoints, public rate limiting. | W3, W6 | PR-C, PR-D |
| **PR-F** | Admin portal: views, CRUD controllers, media management, access management, audit log index, CSRF everywhere, real `AdminAuditService::record()`. | W7 | PR-B, PR-C, PR-D |
| **PR-G** | Mobile catalog: typed home, catalog tabs, movie/series detail screens, profile. | W9 | PR-C |
| **PR-H** | Mobile player + progress sync + resume + UI states. | W10 | PR-E, PR-G |
| **PR-I** | Security sweep (§14), `LogRedactor`, response-shape feature tests, full CI workflow (`.editorconfig`, php-cs-fixer, analysis options, `ci.yml`), composer scripts. | W8, W11 | PR-A..PR-H |

**Parallelizable**: PR-C and PR-D touch disjoint files and can run concurrently. PR-G can begin once PR-C exposes the catalog API contract — model DTOs can be drafted from the API spec ahead of PR-C merging.

## Full API / route checklist (replaces stubs unless noted "done")

| Method | Path | Auth | Status |
|---|---|---|---|
| POST | /api/auth/login | none | done — add rate limit (PR-B) |
| POST | /api/auth/refresh | refresh | partial — add rotation, rate limit (PR-B) |
| POST | /api/auth/logout | refresh | done |
| GET | /api/me | bearer | done |
| GET | /api/home | bearer | stub → implement typed (PR-C) |
| GET | /api/categories | bearer | stub → implement (PR-C) |
| GET | /api/genres | bearer | stub → implement (PR-C) |
| GET | /api/movies | bearer | new (PR-C) |
| GET | /api/movies/{slug} | bearer | new (PR-C) |
| GET | /api/series | bearer | new (PR-C) |
| GET | /api/series/{slug} | bearer | new (PR-C) |
| GET | /api/seasons/{id}/episodes | bearer | new (PR-C) |
| GET | /api/continue-watching | bearer | new (PR-C) |
| POST | /api/playback-sessions | bearer | stub (501) → implement (PR-E) |
| POST | /api/watch-progress | bearer | new (PR-E) |
| POST | /api/watch-progress/mark-watched | bearer | new (PR-E) |
| POST | /api/watch-progress/clear | bearer | new (PR-E) |
| GET | /public/catalog | none + rate-limit | new (PR-C) |
| GET | /public/movies/{slug} | none | new (PR-C) |
| GET | /public/series/{slug} | none | new (PR-C) |
| POST | /public/playback-sessions | none + rate-limit | stub → implement (PR-E) |
| GET | /admin/login | none | stub → real form (PR-B) |
| POST | /admin/login | none + CSRF + rate-limit | new (PR-B) |
| POST | /admin/logout | session + CSRF | new (PR-B) |
| GET | /admin/dashboard | session(admin) | new (PR-F) |
| GET/POST | /admin/movies(/…) | session(admin) + CSRF | new (PR-F) |
| GET/POST | /admin/series(/…) | session(admin) + CSRF | new (PR-F) |
| GET/POST | /admin/seasons(/…) | session(admin) + CSRF | new (PR-F) |
| GET/POST | /admin/episodes(/…) | session(admin) + CSRF | new (PR-F) |
| GET/POST | /admin/categories(/…), /admin/genres(/…) | session(admin) + CSRF | new (PR-F) |
| GET/POST | /admin/users(/…) | session(admin) + CSRF | new (PR-F) |
| POST | /admin/media/upload-url | session(admin) + CSRF | new (PR-F) |
| POST | /admin/media/{id}/sync | session(admin) + CSRF | new (PR-F) |
| POST | /admin/media/{id}/delete | session(admin) + CSRF | new (PR-F) |
| POST | /admin/access/assign, /admin/access/revoke | session(admin) + CSRF | new (PR-F) |
| POST | /admin/share-links/create, /admin/share-links/revoke | session(admin) + CSRF | new (PR-F) |
| GET | /admin/audit-logs | session(admin) | new (PR-F) |

## Risks & decisions

- **Parallel Codex agents** — multiple agents touching migrations risk conflicts. Mitigation: all new schema ships as `003_*` / `004_*`; never edit applied migrations. The forward-only migration runner already enforces this.
- **Heartbeat idempotency** — `POST /api/watch-progress` runs every 20 s per active player. MUST be `INSERT … ON DUPLICATE KEY UPDATE` keyed on `(user_id, playable_type, playable_id)`. Naive insert would race.
- **Forward-only migrations** — no `--rollback` path. Renames/drops ship as add-new → backfill → switch reads → drop-old across separate migrations.
- **JWT library decision** — keep handrolled HMAC for API access tokens (already shipped, tested). Use `openssl_sign` directly for Stream RS256 — no library. Mitigation for crypto risk: pinned payload shape + signature-shape tests + `composer audit` in CI.
- **In-memory vs. Redis rate limiter** — in-memory works only for single-process PHP. Default to Redis when `REDIS_HOST` is set; in-memory fallback for local dev. Production must use Redis.
- **Admin portal scope** — server-rendered with hand-written PHP templates (no Twig). Trade-off: less ergonomic templating in exchange for zero new packages and clearer security review surface.
- **Mobile router** — stay with state-based navigation until detail/player flows force deep linking. `go_router` can be added in a cheap follow-up PR.
- **Test database choice** — MySQL via docker-compose on a separate DB name. Rejecting SQLite-in-memory because the schema uses MySQL-specific syntax.
- **Public catalog cache** — not in spec; defer until traffic warrants. Adding it later is straightforward (CDN edge or in-app cache).

## E2E verification (run after every PR)

1. `docker compose -f docker/docker-compose.yml up -d` → MySQL + Redis up.
2. `cd backend && composer install && composer migrate && composer seed`.
3. `php -S localhost:8080 -t public public/router.php`.
4. `cd mobile && flutter pub get && flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8080`.
5. Login as `admin@example.com / ChangeMe123!`.
6. Browse catalog → open a movie → player creates session → HLS plays → progress posts on heartbeat → resume on second open.
7. Admin: open `http://localhost:8080/admin/login`, log in, create a movie, try `visibility=public` without rights → server rejects (422), flip rights to `owned_by_me` + `public_streaming_enabled=true` → accepted, audit log row written.
8. `cd backend && composer test` → all unit + feature tests pass.
9. `cd mobile && flutter analyze && flutter test` → pass.
10. CI workflow green on push.

### Per-workstream targeted checks

- **W1**: hit `/api/me` with wrong CORS origin → blocked; invalid JSON body → 400 structured error; >5 logins/min from one IP → 429.
- **W2**: refresh-token reuse after rotation → rejected; mobile keeps working seamlessly.
- **W4**: public response body contains no `provider_uid` / `rights_status` / `storage_key`.
- **W5**: `CloudflareStreamServiceTest` verifies JWT header `{alg:'RS256', kid:…}` and payload `{sub, exp}` signed by test PEM; R2 presign rejects `..` keys and wrong content-types.
- **W6**: `POST /api/playback-sessions` for private movie without assignment → 403; with assignment → 200 + signed playback URL with no `provider_uid` in response; public session over rate budget → 429.
- **W7**: admin POST without CSRF → 419/403; admin sets `visibility=public` on `personal_only` content → 422; audit log row inserted on every accepted mutation.
- **W10**: kill access token mid-playback → interceptor refreshes → next request succeeds; force-expire playback session → next heartbeat 401 → mobile handles gracefully (new session or surfaced error).
