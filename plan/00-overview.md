# 00 — Overview, Current State, Milestones

## Already shipped

- `backend/`: PSR-4 PHP 8.3 app skeleton, Router/Request/Response with middleware pipeline, JWT-based `AuthService` (login/refresh/logout/me), `AuthMiddleware`, `AdminMiddleware`, `AccessPolicyService` (matrix-tested), in-memory `WatchProgressService`, forward-only `scripts/migrate.php` with migration tracking, full DB schema for all 14 tables (migrations `001`, `002`).
- `mobile/`: Flutter + Riverpod app with Dio API client (refresh-on-401), secure token storage, login → home shell.
- `docker/`: docker-compose for MySQL 8 + Redis (port 6380) + Adminer.

## Unbuilt (this plan)

- Catalog repositories, transformers, endpoints (public + auth).
- Cloudflare Stream JWT signing + direct upload; R2 SigV4 presigning.
- Playback session flow (auth + public, rate-limited).
- DB-backed watch progress + endpoints.
- Server-rendered admin portal (sessions, CSRF, CRUD, media mgmt, audit log).
- Core hardening: CORS, rate limiter, structured error handler, validator, route params, config boot validation.
- Mobile catalog, detail screens, player, progress sync, resume behavior.
- Security sweep (§14 checklist), log redaction.
- CI workflow + linting configs.

## Dependency graph

```
W1 (core hardening) ──► W2 (auth), W3 (progress), W4 (catalog), W5 (CF), W6 (playback), W7 (admin)
W4 ──► W9 (mobile catalog)
W5 ──► W6
W3 + W6 ──► W10 (mobile player + sync)
W7, W10 ──► W8 (security sweep) ──► W11 (CI + release gate)
```

W1 lands first because every later endpoint depends on uniform error JSON, validated env, CORS for the Flutter origin, and the rate-limiter abstraction.

## Milestones

| Milestone | Workstreams | Done when |
|---|---|---|
| **M1: Foundation hardened** | W1, W2 | API rejects bad CORS, returns structured errors, rate-limits login, rotates refresh tokens, admin session+CSRF login works. |
| **M2: Catalog readable** | W4 | Auth + public catalog endpoints serve typed data; transformers strip `provider_uid` / `rights_status`; access policy enforced per endpoint. |
| **M3: Playback works E2E** | W3, W5, W6 | Signed Stream playback URL issued via `POST /api/playback-sessions`; progress upserts to DB; public variant rate-limited. |
| **M4: Admin operable** | W7 | Admin can log in, CRUD catalog entities, request upload URLs, set visibility under rights guards, see audit log. |
| **M5: Mobile complete** | W9, W10 | Flutter app browses catalog, plays HLS, syncs progress, resumes incomplete content. |
| **M6: Release ready** | W8, W11 | Security sweep clean, CI green on backend + mobile + secret scan, E2E smoke (plan §10) passes. |

## Verification harness used across all workstreams

End-to-end smoke after each PR (full version in [10-pr-sequence-and-risks.md](10-pr-sequence-and-risks.md)):

1. `docker compose -f docker/docker-compose.yml up -d`
2. `cd backend && composer install && composer migrate && composer seed`
3. `php -S localhost:8080 -t public public/router.php`
4. `cd mobile && flutter pub get && flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8080`
5. Login as `admin@example.com / ChangeMe123!`, walk through catalog → detail → player.
6. Admin: `http://localhost:8080/admin/login`, exercise CRUD + rights guards.
7. `composer test` and `flutter analyze && flutter test` both clean.
