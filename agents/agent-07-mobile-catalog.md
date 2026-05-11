# Agent-07 — Mobile Catalog

**Phase**: P3 (parallel with Agent-05, Agent-06)
**Workstream**: W9 — see [`../plan/07-mobile-app.md`](../plan/07-mobile-app.md)
**PR**: PR-G
**Branch**: `agent-07-mobile-catalog`

## Role

Build the Flutter catalog experience: typed home + continue-watching, movie/series list tabs, detail screens, profile. Drop the untyped Map shape that the current home renders.

## Depends on

- **Agent-03 merged**: catalog API endpoints with locked DTO shapes (see Agent-03 contract block).

You can start drafting models from the Agent-03 doc before its PR merges — but do not commit against an unmerged contract.

## Owned files (you create)

```
mobile/lib/features/home/home_models.dart
mobile/lib/features/catalog/catalog_models.dart
mobile/lib/features/catalog/catalog_repository.dart
mobile/lib/features/catalog/catalog_screen.dart
mobile/lib/features/catalog/movie_detail_screen.dart
mobile/lib/features/catalog/series_detail_screen.dart
mobile/lib/features/profile/profile_screen.dart

mobile/test/features/home/home_models_test.dart
mobile/test/features/catalog/catalog_models_test.dart
mobile/test/features/catalog/catalog_screen_test.dart
mobile/test/features/catalog/movie_detail_screen_test.dart
mobile/test/features/profile/profile_screen_test.dart
```

## Shared files (you modify, additive only)

```
mobile/lib/features/home/home_repository.dart   — change return type to HomeData
mobile/lib/features/home/home_screen.dart       — render typed rails + continue-watching
mobile/lib/main.dart                            — wire new routes (state-based navigation)
mobile/pubspec.yaml                             — add any deps (don't add video_player here; Agent-08 owns that)
```

## Forbidden files

- `mobile/lib/core/network/api_client.dart` (Agent-02 finalized; only consume).
- `mobile/lib/features/auth/` (already shipped).
- `mobile/lib/features/player/` (Agent-08).
- Anything under `backend/`.

## Inputs consumed (from Agent-03)

The locked catalog API contracts in [`agent-03-catalog.md`](agent-03-catalog.md):
- `GET /api/home` — typed home + continue-watching.
- `GET /api/movies`, `/api/movies/{slug}` — list + detail.
- `GET /api/series`, `/api/series/{slug}`, `/api/seasons/{id}/episodes`.
- `GET /api/continue-watching`.
- All responses already strip `provider_uid` etc.

## Outputs produced

### `HomeData` model
```dart
class HomeData {
  final List<Rail> rails;
  final List<ContinueWatchingItem> continueWatching;
}
class Rail { final String title; final List<CatalogItem> items; }
class ContinueWatchingItem {
  final String playableType; final int playableId;
  final String title; final String posterUrl;
  final int positionSeconds; final int durationSeconds;
}
```

### `Movie` / `Series` / `Season` / `Episode` models
Mirror the Agent-03 response shapes 1:1. Each ships with `fromJson` + unit tests.

### Screen contract (Agent-08 consumes)
- `movie_detail_screen.dart` exposes a "Play" CTA that navigates to a player route with `{playable_type: 'movie', playable_id: ...}`. Agent-08 implements the destination.
- Same for `series_detail_screen.dart` episode tap.

## Acceptance criteria

- [ ] Login → home shows typed rails + continue-watching strip; no `Map<String, dynamic>` anywhere in `home/`.
- [ ] Catalog tabs (Movies / Series) load paginated lists with poster + title + year.
- [ ] Tap movie → detail screen with synopsis, metadata, Play CTA (CTA wired in Agent-08).
- [ ] Tap series → detail with season picker + episode list.
- [ ] All screens (home, catalog, detail, profile) implement loading / error+retry / empty states.
- [ ] Profile shows name, email, logout button (wired to existing `AuthController`).
- [ ] `flutter analyze` clean; `flutter test` green for all model + widget tests.

## Handoff note template

```
## Handoff (Agent-07)
- Contracts locked:
  - Player route receives {playable_type, playable_id} navigation args
  - HomeData / Movie / Series / Episode model shapes (see lib/features/catalog/catalog_models.dart)
- Pubspec changes: <list>
- Open follow-ups: go_router migration (deferred), search UI (deferred)
```
