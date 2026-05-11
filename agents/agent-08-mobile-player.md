# Agent-08 — Mobile Player + Progress Sync

**Phase**: P4 (single agent)
**Workstream**: W10 — see [`../plan/07-mobile-app.md`](../plan/07-mobile-app.md) (player section)
**PR**: PR-H
**Branch**: `agent-08-mobile-player`

## Role

Build the HLS player, progress heartbeat, and resume behavior. Wire the Play CTAs that Agent-07 left hanging.

## Depends on

- **Agent-05 merged**: `POST /api/playback-sessions` and `POST /api/watch-progress` are live with locked request/response shapes.
- **Agent-07 merged**: detail screens navigate to player with `{playable_type, playable_id}`.

## Owned files (you create)

```
mobile/lib/features/player/playback_session_model.dart
mobile/lib/features/player/player_repository.dart
mobile/lib/features/player/player_controller.dart
mobile/lib/features/player/player_screen.dart
mobile/lib/features/player/watch_progress_service.dart
mobile/lib/features/player/resume_dialog.dart

mobile/test/features/player/playback_session_model_test.dart
mobile/test/features/player/player_controller_test.dart
mobile/test/features/player/watch_progress_service_test.dart
mobile/test/features/player/player_screen_test.dart
```

## Shared files (you modify, additive only)

```
mobile/lib/features/catalog/movie_detail_screen.dart  — wire Play CTA → player route
mobile/lib/features/catalog/series_detail_screen.dart — wire episode tap → player route
mobile/lib/main.dart                                  — register player route
mobile/pubspec.yaml                                   — add video_player (and any HLS helpers)
```

## Forbidden files

- Anything under `backend/`.
- `mobile/lib/core/network/api_client.dart` (do not alter the interceptor).
- `mobile/lib/features/auth/`, `splash/`, `home/` — those are stable.

## Inputs consumed

### From Agent-05
- `POST /api/playback-sessions` request `{playable_type, playable_id}` → response `{playback_url, expires_at, session_id}`.
- `POST /api/watch-progress` request `{playable_type, playable_id, position_seconds, duration_seconds}`.
- 90% server-side completion threshold (you mirror this on the client for UI hints, but the server is authoritative).

### From Agent-07
- Navigation contract: player route receives `playable_type` (string: `movie` | `episode`) + `playable_id` (int).
- `Movie` / `Episode` models include any metadata you might need to display in the player chrome.

### From Agent-02
- `_AuthInterceptor` handles 401 refresh + rotated refresh-token persistence — you do not duplicate.

## Outputs produced

### `PlayerState`
```dart
sealed class PlayerState {}
class PlayerLoading extends PlayerState {}
class PlayerReady extends PlayerState {
  final VideoPlayerController controller;
  final PlaybackSession session;
  final Duration? resumeFrom;   // null = start from 0
}
class PlayerError extends PlayerState { final String message; final Object? cause; }
```

### Heartbeat behavior
- Every 20 s while playing.
- Flush immediately on: pause, seek-complete, app background, completion event.
- Debounce rapid seeks (≥500 ms after the last seek before sending).
- Best-effort queueing if offline (in-memory only — no persistent queue).

### Resume rules
- Fetch progress (from continue-watching cache or a fresh repo call) before opening the player.
- If `completed=false` AND `position_seconds > 5` → `ResumeDialog` ("Resume from M:SS?" / "Start over").
- If `completed=true` → start at 0.
- If no progress row → start at 0.

## Acceptance criteria

- [ ] Tap Play → session created → HLS plays.
- [ ] Pause for 30 s → close app → reopen → resume dialog appears at correct position.
- [ ] Watch through ≥90% → close → next open starts from 0 (completed).
- [ ] Kill access token mid-playback → next session creation refreshes silently → playback continues.
- [ ] Force-expire playback session (back-end stub) → next heartbeat surfaces error gracefully (PlayerError or new session request) — no infinite spinner.
- [ ] All player states render: loading, error+retry, empty (no media asset).
- [ ] `flutter analyze` clean; `flutter test` green.

## Handoff note template

```
## Handoff (Agent-08)
- Pubspec changes: video_player added
- No new contracts produced (terminal consumer)
- Open follow-ups: PiP, Chromecast, AirPlay, offline downloads, subtitle picker — all deferred
```
