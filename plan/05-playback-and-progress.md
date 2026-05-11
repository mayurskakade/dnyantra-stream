# 05 — Playback Sessions + Watch Progress (W3 + W6)

**Blocks**: W10 (mobile player + sync).
**Depends on**: W1 (rate limiter), W4 (catalog repos resolve content), W5 (Stream signing).

## Why

Playback creation is a 501 stub; watch progress is in-memory only. Both must be DB-backed, access-policy enforced, and rate-limited (public) before the mobile player can be wired.

## Deliverables

### 1. Playback session flow

- New `backend/app/Controllers/Api/PlaybackController.php` with `create()` (auth) and `createPublic()` (anonymous).
- Fill out `backend/app/Services/PlaybackService.php::createSession(?User $user, string $type, int $id, ?string $shareToken = null): array`:
  1. Resolve content via the appropriate repository (`MovieRepository` / `EpisodeRepository`).
  2. Resolve attached `media_asset` row (must be present + Stream `provider_uid` populated).
  3. `AccessPolicyService::assertCanWatch(...)` — fail closed with `AuthorizationException` → `403`.
  4. Generate 32-byte random session token; SHA-256 hash.
  5. Insert `playback_sessions` row: `session_token_hash`, `user_id`, `playable_type`, `playable_id`, `expires_at`, `ip`, `user_agent`.
     - Expiry: 60 min authenticated, 30 min anonymous.
  6. Call `CloudflareStreamService::createSignedPlaybackToken($providerUid, $expiresAt)`.
  7. Return `{playback_url, expires_at, session_id}` only. **Never** expose `provider_uid`, `session_token_hash`, or raw token.
- Rate limit `POST /public/playback-sessions` to `20 / 5min / IP` via `RateLimiter`. `POST /api/playback-sessions` is bearer-protected, no extra limit needed (token rate-limit covers it).

**Routes** (add to `routes/api.php` and `routes/public.php`):

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | /api/playback-sessions | bearer | body `{playable_type, playable_id}` |
| POST | /public/playback-sessions | none | body `{playable_type, playable_id, share_token?}`, rate-limited |

### 2. Watch progress — DB-backed

- Verify `watch_progress` has `UNIQUE(user_id, playable_type, playable_id)`. If missing, ship migration `004_watch_progress_unique.sql`.
- New `backend/app/Repositories/WatchProgressRepository.php` (scaffolded in W4):
  - `upsert(int $userId, string $type, int $id, int $position, int $duration, bool $completed): array`
    - Uses MySQL `INSERT … ON DUPLICATE KEY UPDATE position_seconds=VALUES(position_seconds), duration_seconds=VALUES(duration_seconds), completed=VALUES(completed), last_played_at=NOW()`.
  - `clear(int $userId, string $type, int $id): void`
  - `findFor(int $userId, string $type, int $id): ?array`
- Rewrite `backend/app/Services/WatchProgressService.php` to use the repo. **Preserve the existing public API** so the current unit tests still pass; add new tests for DB-backed behavior.
- Completion threshold: `position_seconds / duration_seconds >= 0.9` marks `completed = true`.

### 3. Watch progress endpoints

- New `backend/app/Controllers/Api/WatchProgressController.php`:

| Method | Path | Body | Notes |
|---|---|---|---|
| POST | /api/watch-progress | `{playable_type, playable_id, position_seconds, duration_seconds}` | Upsert. Auto-marks completed at ≥90%. |
| POST | /api/watch-progress/mark-watched | `{playable_type, playable_id}` | Explicit completion. |
| POST | /api/watch-progress/clear | `{playable_type, playable_id}` | Removes the row. |

All bearer-protected. Validate `playable_type ∈ {movie, episode}`, IDs are positive ints, position/duration are non-negative ints with `position <= duration`.

### 4. Tests

- `backend/tests/Unit/PlaybackServiceTest.php`:
  - Private content without assignment → throws `AuthorizationException`.
  - Public content outside its window → throws.
  - Happy path returns shape `{playback_url, expires_at, session_id}` and inserts a session row.
- `backend/tests/Unit/WatchProgressServiceTest.php`:
  - Upsert idempotency.
  - Completion threshold at 0.9.
  - `clear()` removes the row.
- `backend/tests/Feature/PlaybackEndpointsTest.php`:
  - `POST /api/playback-sessions` for unauthorized private content → 403.
  - With assignment → 200 + correct shape, response body contains no `provider_uid`.
  - `POST /public/playback-sessions` over rate budget → 429.

## Files

**Create**: `app/Controllers/Api/PlaybackController.php`, `app/Controllers/Api/WatchProgressController.php`, `app/Repositories/WatchProgressRepository.php` (if not already in W4), `tests/Unit/PlaybackServiceTest.php`, `tests/Unit/WatchProgressServiceTest.php`, `tests/Feature/PlaybackEndpointsTest.php`. Possibly `database/migrations/004_watch_progress_unique.sql`.

**Modify**: `app/Services/PlaybackService.php`, `app/Services/WatchProgressService.php`, `routes/api.php`, `routes/public.php`.

## Acceptance

- Mobile (after W10) can POST a playback session, receive a CF Stream signed URL, and play it.
- Private content without assignment → 403 with `error.code = "forbidden"`.
- Public content within its window → 200; outside window → 403.
- 21st public session from same IP within 5 min → 429.
- `POST /api/watch-progress` heartbeat 100 times per minute is idempotent (single row, latest values).
- Reaching ≥90% sets `completed = true`; subsequent `mark-watched` is a no-op.

## Out of scope

- Cross-device "resume here" syncing beyond what `watch_progress` already provides.
- Adaptive analytics, bitrate logging.
