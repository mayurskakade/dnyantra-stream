# 07 — Mobile App: Catalog + Player + Progress (W9 + W10)

**Blocks**: M5 milestone (mobile-complete).
**Depends on**: W4 (catalog endpoints), W3 + W6 (playback + progress endpoints).

## Why

Mobile currently has login → home shell only, and home renders untyped maps. We need typed catalog browsing, detail screens, an HLS player, progress sync, and resume behavior to complete the user experience.

## Deliverables — W9 (catalog UI)

### 1. Typed home + continue-watching

- `mobile/lib/features/home/home_models.dart` — `HomeData`, `Rail`, `ContinueWatchingItem`.
- Refactor `home_repository.dart` to return `HomeData` (delete the `Map<String, dynamic>` shape).
- Update `home_screen.dart` to render typed rails + continue-watching strip with loading/error/empty states.

### 2. Catalog feature

Under `mobile/lib/features/catalog/`:

- `catalog_models.dart` — `Movie`, `Series`, `Season`, `Episode`, `MediaAsset`, `Category`, `Genre` DTOs with `fromJson`.
- `catalog_repository.dart` — paginated `listMovies`, `listSeries`, `getMovie(slug)`, `getSeries(slug)`, `listEpisodes(seasonId)`.
- `catalog_screen.dart` — tabs (Movies / Series), infinite-scroll list with thumbnail / title / year.
- `movie_detail_screen.dart` — synopsis, metadata, play CTA, continue-from-progress hint.
- `series_detail_screen.dart` — season picker, episode list, play CTA per episode.

### 3. Profile

`mobile/lib/features/profile/profile_screen.dart` — name, email, logout button (already wired in `AuthController`).

### 4. Routing

Stay with state-based navigation (Riverpod + `Navigator.push`) unless deep linking forces the issue. If detail/player flows get unwieldy, introduce `go_router` in a separate small PR.

## Deliverables — W10 (player + progress)

### 1. Player feature

Under `mobile/lib/features/player/`:

- `playback_session_model.dart` — `{playbackUrl, expiresAt, sessionId}`.
- `player_repository.dart` — `createSession(type, id)` → `POST /api/playback-sessions`.
- `player_controller.dart` — `AsyncNotifier<PlayerState>`; requests session, owns the `VideoPlayerController` (package: `video_player`), exposes play/pause/seek.
- `player_screen.dart` — full-screen HLS view with loading / error / empty + retry states. Bottom bar shows scrubber, time remaining.
- On 401 during session creation: bubble up; existing `_AuthInterceptor` refreshes and retries.

### 2. Progress sync

- `mobile/lib/features/player/watch_progress_service.dart`:
  - Heartbeat every 20 s while playing.
  - On pause / seek / app background / completion → flush immediately.
  - `POST /api/watch-progress` with `{playable_type, playable_id, position_seconds, duration_seconds}`.
  - Mark watched on `position/duration >= 0.9` or explicit completion event.
- Debounce rapid seeks; batch heartbeats if offline (queue in memory, flush on reconnect — best-effort).

### 3. Resume behavior

- Before opening the player, fetch `watch_progress` for the item (or pull from continue-watching cache).
- If `completed=false` AND `position_seconds > 5` → prompt "Resume from M:SS?" / "Start over".
- If `completed=true` → start from 0.

### 4. UI states everywhere

Every major screen (home, catalog, detail, player, profile) implements three states: loading, error (with retry), empty. Login already has them; replicate.

### 5. Tests

- `mobile/test/features/catalog/catalog_models_test.dart` — `fromJson` for each DTO.
- `mobile/test/features/player/player_controller_test.dart` — session creation, refresh-on-401, lifecycle.
- Widget tests: home loaded/empty/error, catalog list, player loading state. Use `ProviderScope` overrides for a mocked Dio.

## Files

**Create**:
- `lib/features/home/home_models.dart`
- `lib/features/catalog/{catalog_models,catalog_repository,catalog_screen,movie_detail_screen,series_detail_screen}.dart`
- `lib/features/player/{playback_session_model,player_repository,player_controller,player_screen,watch_progress_service}.dart`
- `lib/features/profile/profile_screen.dart`
- `test/features/**/*.dart` (model + widget tests)

**Modify**:
- `lib/features/home/home_repository.dart`, `home_screen.dart`
- `pubspec.yaml` — add `video_player`

## Acceptance

- Login → home shows typed rails + continue-watching.
- Tap a movie → detail screen → tap play → HLS plays via signed URL.
- Pause for 30 s, close app, reopen → resume prompt appears at correct position.
- Watch ≥90% → next open starts from 0 (completed).
- Kill access token mid-playback → interceptor refreshes silently → playback continues / next session creation succeeds.
- All screens render loading / error+retry / empty states (no white screens, no infinite spinners).

## Out of scope

- Picture-in-picture, AirPlay, Chromecast.
- Offline downloads.
- Subtitles UI beyond what `video_player` provides natively (full subtitle picker can come later if R2 subtitle assets are populated).
