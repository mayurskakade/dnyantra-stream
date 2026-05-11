# Remaining Implementation Plan — Dnyantra Stream

## Context
The repo (`backend/` PHP 8.3 PSR-4 app, `mobile/` Flutter+Riverpod app, `docker/` MySQL/Redis/Adminer) has shipped its foundations: full schema (migrations 001 + 002 cover all 14 tables from IMPLEMENTATION_PLAN.md §3), API JWT auth (`AuthService`, `AuthController`, `AuthMiddleware`, `AdminMiddleware`), an enforceable `AccessPolicyService` with a unit-tested matrix, an in-memory `WatchProgressService`, a basic Router/Request/Response with middleware pipeline, a forward-only `scripts/migrate.php` with migration tracking, a Dio-based mobile API client with refresh-on-401, secure token storage, and a working login → home shell. The rest of IMPLEMENTATION_PLAN.md is unbuilt: catalog endpoints, playback signing, Cloudflare Stream/R2 integration, admin portal CRUD, watch-progress persistence, server-rendered admin UI, mobile catalog/player/progress, CORS/rate limiting, and CI. This plan sequences that remaining work so each workstream unblocks the next without re-doing what is already done.

---

## 1. Workstreams in dependency order

| # | Workstream | Blocks |
|---|---|---|
| W1 | Backend core hardening (config validation, CORS, rate limiter, error handler, request validation) | W2–W8 |
| W2 | Auth completion (refresh-token rotation, admin session+CSRF, rate limit on /login) | W7, W8 |
| W3 | Watch-progress DB persistence + endpoints | W6, W10 |
| W4 | Catalog domain (repositories, DTOs, public+auth endpoints) | W5, W9 |
| W5 | Cloudflare Stream + R2 integration (signing, presign, direct upload) | W6 |
| W6 | Playback session flow (POST /api/playback-sessions, public variant, rate limit) | W10 |
| W7 | Admin portal (server-rendered CRUD, CSRF, audit log writes, media management) | — |
| W8 | Security hardening pass (§14 checklist sweep + log redaction) | release |
| W9 | Mobile catalog + series/movie detail + continue-watching | W10 |
| W10 | Mobile player + progress sync + resume behavior | release |
| W11 | Tests expansion + CI gates + tooling configs | release |

W1 must land first because every subsequent endpoint depends on uniform error JSON, validated env, CORS for the Flutter origin, and the rate-limiter abstraction. W5 unlocks W6; W4 unlocks W9; W6+W3 unlock W10.

---

## 2. Per-section deliverables

### §0 Guiding constraints — enforcement points to add
- Seeder must insert sample movie with `visibility='private'`, `rights_status='personal_only'`.
- `AdminAuditService` (currently stub) must record any visibility/rights mutation before commit.
- Add a guard in admin update paths that rejects `visibility='public'` unless `rights_status ∈ {owned_by_me, licensed_public, public_domain, creative_commons}` and `public_streaming_enabled=true` — server-side, not UI-only.

### §2 Backend core framework (gaps remaining)
- `app/Core/Router.php`: extend `add()` to accept path patterns with `{param}` segments; populate `Request::setAttribute('route.params', …)`. Current router only does exact-string match.
- `app/Core/ErrorHandler.php` (new): global handler installed in `public/index.php` and `public/admin.php`; structured JSON for API paths, HTML for admin paths. Catches `ValidationException`, `AuthorizationException`, `NotFoundException`, generic `Throwable`.
- `app/Core/Validator.php` (new): whitelist-enum aware (visibility, rights_status, status, role); used by controllers.
- `app/Config/Config.php`: add `Config::require(string $key): string` that throws on boot if missing. Call `Config::bootValidate(['DB_HOST','JWT_SECRET','CLOUDFLARE_ACCOUNT_ID','CLOUDFLARE_STREAM_API_TOKEN','CLOUDFLARE_STREAM_SIGNING_KEY_PEM','R2_ACCOUNT_ID','R2_ACCESS_KEY_ID','R2_SECRET_ACCESS_KEY','R2_BUCKET','APP_URL','API_ALLOWED_ORIGINS'])` in entrypoints.
- `app/Middleware/CorsMiddleware.php` (new): origin allow-list from `API_ALLOWED_ORIGINS`, methods, headers; preflight short-circuits.
- `app/Services/RateLimiter.php` (new): interface + `RedisRateLimiter` and `InMemoryRateLimiter` fallback. Bind via container/factory keyed on env. Use for `POST /api/auth/login`, `POST /public/playback-sessions`, and refresh.
- `app/Core/Container.php` (optional, light): simple service locator so middleware can resolve `RateLimiter`/`AuthService` without globals — only if needed; otherwise stick with static factories.

### §4 Auth — remaining
- Refresh-token **rotation**: `AuthService::refresh()` must revoke the supplied refresh token and issue a new one in the same transaction. Mobile interceptor already retries once on 401, but it stores both tokens — server must return `{access_token, refresh_token, expires_in}` from `/api/auth/refresh`. Update `AuthController::refresh` and `mobile/lib/core/network/api_client.dart` _AuthInterceptor accordingly.
- **Admin session auth**: replace stub `/admin/login` GET page with:
  - `app/Controllers/Admin/AdminAuthController.php` — `showLogin`, `login`, `logout`.
  - `app/Services/SessionService.php` — PHP `session_start()` with `cookie_httponly=1`, `cookie_secure=1` (env-gated), `cookie_samesite=Lax`, regenerate id on login.
  - `app/Middleware/AdminSessionMiddleware.php` — replaces `AuthMiddleware` for `/admin/*` routes.
  - `app/Services/CsrfService.php` — token issued in session, validated on every admin POST.
- Inactive account lockout: extend `AuthService::login()` to reject `is_active=0` with a distinct error code.
- Optional 2FA placeholder: add `users.totp_secret` nullable column in a new migration `003_admin_2fa.sql`; service stub `TwoFactorService` (no enforcement yet).

### §5 Access policy & rights enforcement — gaps
- `AccessPolicyService` already covers the matrix. Remaining: wire it into every catalog/playback endpoint and into admin mutation guards. Add `AccessPolicyService::assertCanWatch(...)` throwing `AuthorizationException` for ergonomic call sites.
- Audit log writes — implement `AdminAuditService::record(adminId, action, entityType, entityId, before, after, request)` that inserts to `admin_audit_logs`; call from every admin write path.

### §6 Catalog domain
- New: `app/Repositories/{CategoryRepository,GenreRepository,MovieRepository,SeriesRepository,SeasonRepository,EpisodeRepository,WatchProgressRepository,UserContentAccessRepository,ShareLinkRepository}.php` — PDO prepared statements only.
- Fill out `app/Services/CatalogService.php` (currently empty stub) with `home()`, `listMovies()`, `getMovie(slug, ?user)`, `listSeries()`, `getSeries(slug, ?user)`, `listSeasons(seriesId)`, `listEpisodes(seasonId)`, `continueWatching(user)`. Public variants filter by `is_listed_publicly=1` AND `AccessPolicyService::isPublicAllowed()`.
- DTO transformers in `app/Http/Transformers/` (new dir): `MovieTransformer`, `SeriesTransformer`, `EpisodeTransformer`, `MediaAssetTransformer`. **Never** include `provider_uid`, raw `storage_key`, signing tokens, or `rights_status` in public responses.
- Replace stubs in `routes/api.php` and add `routes/public.php`:
  - `GET /api/home` (auth) — featured + continue-watching for user.
  - `GET /api/categories`, `/api/genres`, `/api/movies`, `/api/series` (auth) — paginated.
  - `GET /api/movies/{slug}`, `/api/series/{slug}` (auth) — with seasons/episodes.
  - `GET /api/continue-watching` (auth).
  - `GET /public/catalog`, `/public/movies/{slug}`, `/public/series/{slug}` — listed public only.

### §7 Playback session flow
- `app/Controllers/Api/PlaybackController.php` (new) with `create()` and `createPublic()`.
- `app/Services/PlaybackService.php` (currently empty): `createSession(?user, type, id, ?shareToken)`:
  1. Resolve content + media asset.
  2. `AccessPolicyService::canWatch(...)` — fail closed.
  3. Generate session token (32-byte random), hash with SHA-256, insert `playback_sessions` row with expiry (30 min anonymous, 60 min authenticated).
  4. Call `CloudflareStreamService::createSignedPlaybackToken(uid, expiresAt)`.
  5. Return `{playback_url, expires_at, session_id}` only.
- Rate limit `POST /public/playback-sessions` to e.g. 20 req / 5 min / IP via `RateLimiter`.

### §8 Cloudflare Stream integration
- Implement `CloudflareStreamService::createSignedPlaybackToken($videoUid, $expiresAt)`: JWT (RS256) signed with `CLOUDFLARE_STREAM_SIGNING_KEY_PEM` per CF Stream docs (`kid`, `sub=videoUid`, `exp`, optional `accessRules`). Use `openssl_sign` directly — no library needed.
- Implement `createDirectUploadUrl(meta)` via `POST https://api.cloudflare.com/client/v4/accounts/{accountId}/stream/direct_upload` using `CLOUDFLARE_STREAM_API_TOKEN`; returns `{upload_url, uid}`.
- Implement `getVideoStatus(uid)` GET, `deleteVideo(uid)` DELETE.
- Tests: `tests/Unit/CloudflareStreamServiceTest.php` — JWT signature shape, fail-closed on missing PEM, fail-closed on missing token. Use mock HTTP client.

### §9 Cloudflare R2 integration
- Implement SigV4 in `R2Service::createPresignedUploadUrl()` / `createPresignedReadUrl()` using `R2_ACCOUNT_ID`/`R2_ACCESS_KEY_ID`/`R2_SECRET_ACCESS_KEY`/`R2_BUCKET`. Endpoint: `https://{account}.r2.cloudflarestorage.com/{bucket}/{key}` with query-param signing.
- Whitelist key prefixes: `posters/`, `banners/`, `subtitles/` only. Reject `..`, leading `/`, anything outside whitelist.
- Whitelist content-types per prefix (e.g. posters: `image/jpeg|image/png|image/webp`).
- Implement `deleteObject(key)` via authenticated DELETE.
- Tests: signature canonical-request shape, expired-URL clock handling, rejected key shapes.

### §10 Watch progress
- Migration `004_watch_progress_unique.sql`: ensure `UNIQUE(user_id, playable_type, playable_id)` index on `watch_progress` (likely present in 001 — verify; if not, add).
- Replace in-memory `WatchProgressService` with DB-backed via new `WatchProgressRepository`. Keep existing service API so unit tests still pass; add new tests for DB-backed upsert + completion threshold.
- New `app/Controllers/Api/WatchProgressController.php`:
  - `POST /api/watch-progress` (auth) — body `{playable_type, playable_id, position_seconds, duration_seconds}`. Upsert. Mark completed at `position/duration >= 0.9`.
  - `POST /api/watch-progress/mark-watched` (auth) — explicit completion.
  - `POST /api/watch-progress/clear` (auth).

### §11 Admin portal (server-rendered PHP)
- New view layer under `app/Views/` using plain PHP templates: `layouts/admin.php`, partials for nav/flash/csrf. No framework.
- New controllers under `app/Controllers/Admin/`:
  - `DashboardController` (metrics: counts of users, movies, series, sessions today).
  - `MoviesController`, `SeriesController`, `SeasonsController`, `EpisodesController`, `CategoriesController`, `GenresController`, `UsersController` — index/create/edit/update/delete.
  - `MediaController` — trigger direct upload URL (Stream), status sync, delete media asset.
  - `AccessController` — `user_content_access` assignments, visibility mutation, share-link create/revoke.
  - `AuditLogController` — index with filters (admin user, entity type, date range).
- All POSTs validate CSRF via `CsrfService`. Every write hits `AdminAuditService::record()`.
- Replace stub `routes/admin.php` with full route table; mount `AdminSessionMiddleware` + (for non-login routes) role check.

### §12 Flutter — beyond foundation
Already in: `core/network/api_client.dart`, `core/storage/token_storage.dart`, `features/auth/*`, `features/splash/*`, `features/home/*` (untyped). Remaining vertical slices:

1. **Typed home + continue-watching** — replace `HomeRepository`'s untyped `Map` with `HomeData { List<Rail> rails; List<ContinueWatching> continueWatching }`. Update `features/home/home_screen.dart` to render typed rails.
2. **Catalog** — `features/catalog/`: `catalog_repository.dart` (list movies/series, paginated), `catalog_models.dart` (Movie, Series, Season, Episode, MediaAsset DTOs), `catalog_screen.dart` (tabs Movies/Series), `movie_detail_screen.dart`, `series_detail_screen.dart` (with season picker + episode list).
3. **Player** — `features/player/`:
   - `playback_session_model.dart` — `{playbackUrl, expiresAt, sessionId}`.
   - `player_repository.dart` — POST `/api/playback-sessions`.
   - `player_controller.dart` — `AsyncNotifier<PlayerState>`; requests session, owns `VideoPlayerController`.
   - `player_screen.dart` — full-screen HLS view; loading/error/empty states.
   - On 401 from session creation: bubble up; interceptor handles refresh.
4. **Progress sync** — `features/player/watch_progress_service.dart`: periodic heartbeat every 20 s while playing + on pause/seek/background/complete. POSTs to `/api/watch-progress`. Mark watched on `>=90%` or explicit completion event.
5. **Resume behavior** — on opening a playable: if `watch_progress.completed=false` and `position_seconds > 5`, prompt or auto-resume; if completed, restart by default.
6. **UI states** — all major screens must implement loading / error (with retry) / empty. Already done in home; replicate in catalog, detail, player.
7. **Routing** — introduce `go_router` only if state-based navigation becomes painful with detail/player flows. Acceptable to defer.
8. **Profile** — minimal: name, email, logout.

### §13 Testing strategy — gaps
- Backend unit: complete `AccessPolicyServiceTest` cases for archived state, share-link expired/exhausted, public window edges. Add `CloudflareStreamServiceTest`, `R2ServiceTest`, `PlaybackServiceTest`, `WatchProgressServiceTest` (DB), `AdminAuditServiceTest`, `CsrfServiceTest`.
- Backend feature tests: spin up SQLite or test MySQL fixture; test `POST /api/auth/login` happy + 401, `POST /api/playback-sessions` for both auth and public, `POST /api/watch-progress` upsert, public catalog filtering, admin CSRF rejection.
- Flutter: model `fromJson` tests for each DTO; widget tests for login (exists), home loaded/empty/error, catalog list, player loading. Use `ProviderScope` overrides for Dio mock.

### §14 Security hardening checklist sweep
- Audit every PDO call site for `prepare`/`execute` (no string interpolation). Grep for `->query(` and `->exec(` and verify no user input.
- Confirm `password_hash` uses `PASSWORD_DEFAULT` and `password_verify` is the only check path.
- All admin POSTs covered by CSRF middleware.
- All controller inputs go through `Validator` with whitelist enums for `visibility`, `rights_status`, `status`, `role`.
- Hashed tokens: refresh_tokens.token_hash, share_links.token_hash, playback_sessions.session_token_hash — verify hashing on insert and constant-time comparison on read.
- Rate limit applied: `/api/auth/login`, `/api/auth/refresh`, `/public/playback-sessions`.
- Add `app/Support/LogRedactor.php`: scrub `password`, `Authorization`, `access_token`, `refresh_token`, `token_hash` from any structured log.
- `.env.example` audit: only placeholders, no real keys. Add a CI grep that fails if `.env` is committed.

---

## 3. API & route checklist (still to implement or replace stubs)

| Method | Path | Auth | Status |
|---|---|---|---|
| POST | /api/auth/login | none | done — add rate limit |
| POST | /api/auth/refresh | refresh | partial — add rotation, rate limit |
| POST | /api/auth/logout | refresh | done |
| GET | /api/me | bearer | done |
| GET | /api/home | bearer | stub → implement typed |
| GET | /api/categories | bearer | stub → implement |
| GET | /api/genres | bearer | stub → implement |
| GET | /api/movies | bearer | new |
| GET | /api/movies/{slug} | bearer | new |
| GET | /api/series | bearer | new |
| GET | /api/series/{slug} | bearer | new |
| GET | /api/seasons/{id}/episodes | bearer | new |
| GET | /api/continue-watching | bearer | new |
| POST | /api/playback-sessions | bearer | stub (501) → implement |
| POST | /api/watch-progress | bearer | new |
| POST | /api/watch-progress/mark-watched | bearer | new |
| POST | /api/watch-progress/clear | bearer | new |
| GET | /public/catalog | none + rate-limit | new |
| GET | /public/movies/{slug} | none | new |
| GET | /public/series/{slug} | none | new |
| POST | /public/playback-sessions | none + rate-limit | stub (501) → implement |
| GET | /admin/login | none | stub → real form |
| POST | /admin/login | none + CSRF + rate-limit | new |
| POST | /admin/logout | session + CSRF | new |
| GET | /admin/dashboard | session(admin) | stub → real |
| GET/POST | /admin/movies(/...) | session(admin)+CSRF | new |
| GET/POST | /admin/series(/...) | session(admin)+CSRF | new |
| GET/POST | /admin/seasons(/...) | session(admin)+CSRF | new |
| GET/POST | /admin/episodes(/...) | session(admin)+CSRF | new |
| GET/POST | /admin/categories(/...), /admin/genres(/...) | session(admin)+CSRF | new |
| GET/POST | /admin/users(/...) | session(admin)+CSRF | new |
| POST | /admin/media/upload-url | session(admin)+CSRF | new |
| POST | /admin/media/{id}/sync | session(admin)+CSRF | new |
| POST | /admin/media/{id}/delete | session(admin)+CSRF | new |
| POST | /admin/access/assign, /admin/access/revoke | session(admin)+CSRF | new |
| POST | /admin/share-links/create, /admin/share-links/revoke | session(admin)+CSRF | new |
| GET | /admin/audit-logs | session(admin) | new |

---

## 4. Integration items

- **Cloudflare Stream**: real JWT signing in `CloudflareStreamService::createSignedPlaybackToken` (RS256 + PEM); direct upload URL via API token; fail-closed if env missing. Reuse existing fail-closed exceptions.
- **R2**: SigV4 presigned PUT/GET in `R2Service`; key/content-type whitelist; short TTL (≤ 15 min upload, ≤ 60 min read).
- **Signed playback**: only `PlaybackService` issues tokens. Mobile and public clients never see `provider_uid` — transformers strip it.
- **Rate limits**: `RateLimiter` abstraction with Redis backend (compose already provisions Redis on 6380) and in-memory fallback. Applied at: login, refresh, public playback sessions, public catalog.
- **CORS**: `CorsMiddleware` reads `API_ALLOWED_ORIGINS` (csv); applied globally to `/api/*` and `/public/*`. Preflight short-circuits with 204.
- **Admin CSRF + sessions**: PHP native sessions, regenerate id on login, `SameSite=Lax`, `Secure` flag in prod, idle timeout, `CsrfService` issues per-session token reused across forms.

---

## 5. Flutter vertical slices (after foundation)

1. **Typed home/continue-watching** — replaces untyped map rendering; reuses existing `homeProvider`.
2. **Catalog browse** — Movies + Series tabs; paginated list with thumbnail/title/year.
3. **Detail screens** — Movie detail (synopsis + play CTA); Series detail (seasons drawer + episode list).
4. **Player** — request session, play HLS via `video_player`, manage controller lifecycle.
5. **Progress sync** — heartbeat every 20 s + on pause/seek/background/complete; `>=90%` marks watched.
6. **Resume behavior** — auto-resume incomplete, restart completed.
7. **UI states everywhere** — loading / error+retry / empty.
8. **Profile + logout** — minimal screen; logout already wired in `AuthController`.

Each slice ships with: model `fromJson` tests, widget test of loaded/empty/error.

---

## 6. CI & tooling

- `.editorconfig` at repo root (LF, 4-space PHP / 2-space Dart, trim trailing whitespace).
- `backend/.php-cs-fixer.php` — PSR-12 + strict types.
- `mobile/analysis_options.yaml` — already exists; promote to `package:flutter_lints/flutter.yaml` + opt into `prefer_relative_imports`, `unawaited_futures`, `avoid_print`.
- `.github/workflows/ci.yml`:
  - `backend` job: PHP 8.3, `composer install`, `composer test`, `vendor/bin/php-cs-fixer fix --dry-run`.
  - `mobile` job: Flutter stable, `flutter pub get`, `flutter analyze`, `flutter test`.
  - `security` job: grep for `.env` commits, secret patterns.
- Gate PRs on all three jobs.

---

## 7. Suggested PR sequence

Mapped to IMPLEMENTATION_PLAN.md §15 but adjusted: PR-1's "full schema + migration runner" is already done.

| PR | Scope | Workstreams |
|---|---|---|
| **PR-A** | Core hardening: route params, ErrorHandler, Validator, Config::require boot validation, CorsMiddleware, RateLimiter abstraction (Redis+memory), structured error JSON. | W1 |
| **PR-B** | Auth completion: refresh-token rotation (+ mobile interceptor update), admin session login + CSRF, login/refresh rate limiting, inactive-account lockout, seeders verifying private-default sample. | W2 |
| **PR-C** | Catalog domain: repositories, transformers, CatalogService, /api/* and /public/* read endpoints, access-policy enforcement at every endpoint. | W4 |
| **PR-D** | Cloudflare Stream JWT signing + R2 SigV4 + tests. | W5 |
| **PR-E** | Playback sessions (auth + public), watch-progress DB persistence + endpoints, public rate limiting. | W3, W6 |
| **PR-F** | Admin portal: views, CRUD controllers, media management, access management, audit log index, CSRF everywhere, AdminAuditService writes. | W7 |
| **PR-G** | Mobile catalog + typed home + detail screens. | W9 |
| **PR-H** | Mobile player + progress sync + resume + profile. | W10 |
| **PR-I** | Security sweep (§14), log redactor, feature tests, full CI workflow, docs. | W8, W11 |

PR-D and PR-C can run in parallel (independent files); PR-E depends on both.

---

## 8. Risks & decisions

- **Parallel Codex agents**: Codex already landed `002_add_access_and_session_tables.sql` and migration tracking. Risk of conflicting migrations if another agent edits 001. Mitigation: all new schema as `003_*`/`004_*` migrations only; never edit applied files. Migration tracking already enforces this.
- **Idempotency**: `POST /api/watch-progress` is a high-frequency heartbeat — must be an UPSERT keyed on `(user_id, playable_type, playable_id)`, never an insert that races. Use MySQL `INSERT … ON DUPLICATE KEY UPDATE`.
- **Migration strategy**: forward-only. Any column rename/drop must ship as add-new → backfill → switch reads → drop-old across separate migrations. No `--rollback` path.
- **JWT library decision**: stick with handrolled HMAC for API access tokens (already shipped) but use `openssl_sign` directly for Stream RS256 — no library required. Risk: cryptography handrolling. Mitigation: pin payload shape, add signature-shape tests, run `composer audit` in CI.
- **Custom JWT vs. firebase/php-jwt**: defer firebase/php-jwt unless verification grows complex; current `AuthService` HMAC implementation is sufficient and already tested.
- **In-memory vs. Redis rate limiter**: in-memory only works for single-process PHP. Production must use Redis. Local docker-compose already provisions Redis (port 6380). Default to Redis when `REDIS_HOST` is set; fall back to in-memory otherwise.
- **Admin portal scope**: server-rendered with hand-written PHP templates (no Twig) keeps the dependency surface tiny. Acceptable trade-off: less ergonomic templating in exchange for zero new packages.
- **Mobile router**: stay with state-based navigation until detail+player flows force deep linking; introducing `go_router` mid-PR is cheap.
- **Public catalog cache**: not in spec; defer. Add only if public traffic warrants.
- **Test database**: choose between SQLite-in-memory (fast, but MySQL-specific SQL won't run) or ephemeral MySQL via docker-compose. Recommendation: MySQL — the schema uses MySQL-specific syntax (FULLTEXT, JSON columns if any). Run feature tests against the same compose-provisioned MySQL but on a separate database name.

---

## Critical files to modify / create

**Backend new:**
- `app/Core/ErrorHandler.php`, `app/Core/Validator.php`, `app/Core/Container.php` (optional)
- `app/Middleware/CorsMiddleware.php`, `app/Middleware/AdminSessionMiddleware.php`, `app/Middleware/CsrfMiddleware.php`
- `app/Services/RateLimiter.php` (interface + Redis + InMemory), `app/Services/SessionService.php`, `app/Services/CsrfService.php`, `app/Services/TwoFactorService.php` (stub)
- `app/Repositories/{Category,Genre,Movie,Series,Season,Episode,WatchProgress,UserContentAccess,ShareLink,PlaybackSession,AdminAuditLog}Repository.php`
- `app/Http/Transformers/{Movie,Series,Episode,MediaAsset,Category,Genre}Transformer.php`
- `app/Controllers/Api/{Catalog,Playback,WatchProgress}Controller.php`
- `app/Controllers/Admin/{AdminAuth,Dashboard,Movies,Series,Seasons,Episodes,Categories,Genres,Users,Media,Access,AuditLog}Controller.php`
- `app/Views/admin/**/*.php`
- `app/Support/LogRedactor.php`
- `routes/public.php`
- `database/migrations/003_admin_2fa.sql`, `004_*` (only if gaps found)
- `database/seeders/` PHP seeders for admin user + sample private movie

**Backend modify:**
- `app/Core/Router.php` — add `{param}` route params
- `app/Core/Request.php` — expose route params via `getRouteParam(string $name)`
- `app/Config/Config.php` — add `require()` + `bootValidate()`
- `app/Services/AuthService.php` — refresh-token rotation
- `app/Services/CloudflareStreamService.php` — real JWT signing, real upload URL
- `app/Services/R2Service.php` — real SigV4
- `app/Services/PlaybackService.php` — real session creation
- `app/Services/WatchProgressService.php` — DB-backed (keep existing API)
- `app/Services/AdminAuditService.php` — real `record()`
- `routes/api.php` — replace stubs with real controllers
- `routes/admin.php` — full admin route table
- `public/index.php`, `public/admin.php` — install error handler + boot validation
- `mobile/lib/core/network/api_client.dart` — handle rotated refresh token
- `mobile/lib/features/home/*` — typed DTOs
- New `mobile/lib/features/{catalog,player,profile}/*`

**Tooling new:**
- `.editorconfig`, `backend/.php-cs-fixer.php`, `.github/workflows/ci.yml`

---

## Verification

End-to-end smoke after each PR:

1. `docker compose -f docker/docker-compose.yml up -d` → MySQL + Redis up.
2. `cd backend && composer install && composer migrate && composer seed` → schema + seed admin/sample movie.
3. `php -S localhost:8080 -t public public/router.php` → API up.
4. `cd mobile && flutter pub get && flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8080` → login as `admin@example.com / ChangeMe123!`.
5. Browse catalog → tap movie → player creates session via backend → HLS plays → progress posts at heartbeat intervals → resume on second open.
6. Admin: `http://localhost:8080/admin/login` → log in → create movie → set `visibility=public` *without* clearing rights → server rejects → flip rights to `owned_by_me` + `public_streaming_enabled=true` → accepts → audit log records diff.
7. `cd backend && composer test` → all unit + feature tests pass.
8. `cd mobile && flutter analyze && flutter test` → passes.
9. CI workflow green on push.

Per-workstream checks:
- W1: hit `/api/me` with wrong CORS origin → blocked; invalid JSON body → 400 with structured error; >5 logins/min from one IP → 429.
- W5: `CloudflareStreamServiceTest` verifies JWT header `{alg:'RS256', kid:…}` and payload `{sub, exp}` signed by test PEM.
- W6: `POST /api/playback-sessions` for private movie without assignment → 403; with assignment → 200 with `playback_url` (no `provider_uid` in body); public session without rate budget → 429.
- W7: admin POST without CSRF token → 419/403; admin sets public on personal_only content → 422; audit log row inserted.
- W10: kill the access token mid-playback → interceptor refreshes → playback uninterrupted; force-expire playback session → next heartbeat 401 → resume handled gracefully.
