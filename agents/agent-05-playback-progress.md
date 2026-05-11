# Agent-05 — Playback Sessions + Watch Progress

**Phase**: P3 (parallel with Agent-06, Agent-07)
**Workstreams**: W3 + W6 — see [`../plan/05-playback-and-progress.md`](../plan/05-playback-and-progress.md)
**PR**: PR-E
**Branch**: `agent-05-playback-progress`

## Role

Build the playback session flow (auth + public, rate-limited) and replace in-memory watch progress with DB-backed upsert. This is the integration point between catalog, access policy, and Cloudflare Stream.

## Depends on

- **Agent-03 merged**: catalog repositories resolve content; `WatchProgressRepository` scaffolded.
- **Agent-04 merged**: `CloudflareStreamService::createSignedPlaybackToken` available.
- (Transitively Agent-01: `RateLimiter`, `Validator`, exceptions.)

## Owned files (you create)

```
backend/app/Controllers/Api/PlaybackController.php
backend/app/Controllers/Api/WatchProgressController.php
backend/app/Repositories/PlaybackSessionRepository.php
backend/tests/Unit/PlaybackServiceTest.php
backend/tests/Unit/WatchProgressServiceDbTest.php
backend/tests/Feature/PlaybackEndpointsTest.php
backend/tests/Feature/WatchProgressEndpointsTest.php
backend/database/migrations/004_watch_progress_unique.sql   (only if missing in 001)
```

## Shared files (you modify, additive only)

```
backend/app/Services/PlaybackService.php           — implement (currently empty stub)
backend/app/Services/WatchProgressService.php      — replace in-memory with DB-backed (preserve public API)
backend/app/Repositories/WatchProgressRepository.php — fill in (scaffolded by Agent-03)
backend/app/Services/AccessPolicyService.php       — add assertCanWatch() if not already added
backend/routes/api.php                             — POST /api/playback-sessions, /api/watch-progress/*
backend/routes/public.php                          — POST /public/playback-sessions (rate-limited)
```

## Forbidden files

`AuthService` / `SessionService` / `CsrfService` (Agent-02). `CloudflareStreamService` / `R2Service` implementations (Agent-04 owns; only call them). Catalog repositories/transformers (Agent-03 owns; only consume).

## Inputs consumed

- From Agent-01: `RateLimiter` (for `POST /public/playback-sessions`), `Validator`, exceptions.
- From Agent-03: `MovieRepository`, `EpisodeRepository`, `WatchProgressRepository` scaffold, `AccessPolicyService`.
- From Agent-04: `CloudflareStreamService::createSignedPlaybackToken($uid, $exp)`.

## Outputs produced (contracts for Agent-08 mobile player)

### `POST /api/playback-sessions` (bearer)
Request:
```json
{ "playable_type": "movie", "playable_id": 1 }
```
Response:
```json
{ "playback_url": "https://...m3u8?token=...", "expires_at": 1736000000, "session_id": "uuid-or-hash-prefix" }
```
**Never** includes `provider_uid`, `session_token_hash`, raw session token.

### `POST /public/playback-sessions` (none, rate-limited)
Same shape, accepts optional `share_token`.

### `POST /api/watch-progress`
Request:
```json
{ "playable_type": "movie", "playable_id": 1, "position_seconds": 1234, "duration_seconds": 5400 }
```
Response:
```json
{ "completed": false, "position_seconds": 1234 }
```

### Completion threshold
`position / duration >= 0.9` → `completed=true` (server enforced).

### Rate limit on public playback
`20 / 5min / IP`. Over budget → 429 with `Retry-After`.

### Session expiry
Authenticated: 60 min. Anonymous: 30 min.

## Acceptance criteria

- [ ] `POST /api/playback-sessions` for private content without assignment → 403, error code `forbidden`.
- [ ] With assignment → 200 with signed URL; response body contains no `provider_uid`.
- [ ] `POST /public/playback-sessions` over rate budget → 429 with `Retry-After`.
- [ ] Session token is hashed (SHA-256) in `playback_sessions.session_token_hash`; raw token never persisted.
- [ ] `POST /api/watch-progress` is idempotent (100 calls → 1 row, latest values).
- [ ] Reaching `>=0.9` ratio sets `completed=true`.
- [ ] `POST /api/watch-progress/clear` removes the row.
- [ ] `WatchProgressService` public API unchanged from Phase 1 — existing tests still pass.

## Handoff note template

```
## Handoff (Agent-05)
- Contracts locked:
  - POST /api/playback-sessions request/response
  - POST /api/watch-progress request/response
  - 90% completion threshold (server enforced)
- Migrations: 004_watch_progress_unique.sql (only if needed; check first)
- Open follow-ups: cross-device "resume here" syncing — already covered by watch_progress; nothing extra needed
```
