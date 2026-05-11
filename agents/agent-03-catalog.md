# Agent-03 — Catalog Domain

**Phase**: P2 (parallel with Agent-02, Agent-04)
**Workstream**: W4 — see [`../plan/03-catalog-domain.md`](../plan/03-catalog-domain.md)
**PR**: PR-C
**Branch**: `agent-03-catalog`

## Role

Build the catalog read-side: repositories, public-safe transformers, `CatalogService`, and the full `/api/*` + `/public/*` read endpoint surface. This is the largest P2 agent by file count.

## Depends on

- **Agent-01 merged** (consumes `Router` `{param}` support, `Validator`, `ErrorHandler`, `NotFoundException`).

## Owned files (you create)

```
backend/app/Repositories/CategoryRepository.php
backend/app/Repositories/GenreRepository.php
backend/app/Repositories/MovieRepository.php
backend/app/Repositories/SeriesRepository.php
backend/app/Repositories/SeasonRepository.php
backend/app/Repositories/EpisodeRepository.php
backend/app/Repositories/UserContentAccessRepository.php
backend/app/Repositories/ShareLinkRepository.php
backend/app/Http/Transformers/MovieTransformer.php
backend/app/Http/Transformers/SeriesTransformer.php
backend/app/Http/Transformers/SeasonTransformer.php
backend/app/Http/Transformers/EpisodeTransformer.php
backend/app/Http/Transformers/MediaAssetTransformer.php
backend/app/Http/Transformers/CategoryTransformer.php
backend/app/Http/Transformers/GenreTransformer.php
backend/app/Controllers/Api/CatalogController.php
backend/routes/public.php
backend/tests/Unit/MovieRepositoryTest.php
backend/tests/Unit/SeriesRepositoryTest.php
backend/tests/Unit/TransformerLeakTest.php
backend/tests/Feature/CatalogEndpointsTest.php
```

## Shared files (you modify, additive only)

```
backend/app/Services/CatalogService.php             — fill out (currently empty stub)
backend/app/Services/AccessPolicyService.php        — add ergonomic helpers (filterListedForUser, assertCanView)
backend/routes/api.php                              — add /api/home, /api/movies, /api/series, etc.
backend/public/index.php                            — mount routes/public.php
```

## Forbidden files

`AuthService`, `SessionService`, `CsrfService` (Agent-02). `CloudflareStreamService`, `R2Service` (Agent-04). `PlaybackService`, `PlaybackController`, `WatchProgressService`, `WatchProgressController` (Agent-05). `WatchProgressRepository` is a special case — scaffold it as part of this agent but leave service logic to Agent-05.

## Inputs consumed (from Agent-01)

- `Router::add('GET', '/api/movies/{slug}', ...)` + `Request::getRouteParam('slug')`.
- `Validator` for query params (page, per_page, filter enums).
- `ErrorHandler` surfaces `NotFoundException` as 404 JSON.

## Outputs produced (contract for Agent-07 mobile catalog)

### Movie list response (`GET /api/movies`)
```json
{
  "data": [
    { "id": 1, "slug": "...", "title": "...", "year": 2020, "poster_url": "...", "duration_seconds": 5400 }
  ],
  "page": 1, "per_page": 20, "total": 42
}
```

### Movie detail response (`GET /api/movies/{slug}`)
```json
{
  "id": 1, "slug": "...", "title": "...", "synopsis": "...", "year": 2020,
  "poster_url": "...", "banner_url": "...", "duration_seconds": 5400,
  "categories": [...], "genres": [...],
  "media_asset": { "id": 7, "type": "video" }   // NO provider_uid, NO storage_key
}
```

### Series detail response
```json
{
  "id": 1, "slug": "...", "title": "...", "synopsis": "...",
  "seasons": [
    { "id": 11, "number": 1, "episode_count": 8 }
  ]
}
```

### Forbidden response keys (anywhere in public/auth catalog responses)
`provider_uid`, `storage_key`, `rights_status`, `public_streaming_enabled`, `visibility` (controversial — leave out unless explicitly needed by mobile; default to omit).

## Acceptance criteria

- [ ] All catalog endpoints from [`../plan/03-catalog-domain.md`](../plan/03-catalog-domain.md) §4 return real data.
- [ ] `GET /api/movies/{slug}` for a private movie the user lacks assignment for → 404 (avoid existence leak; not 403).
- [ ] `GET /public/catalog` returns only `is_listed_publicly=1` + within public window.
- [ ] `TransformerLeakTest` asserts none of `provider_uid` / `storage_key` / `rights_status` appears in any transformer output for any visibility.
- [ ] Continue-watching sorted by `last_played_at` desc.
- [ ] Pagination works on listMovies/listSeries.

## Handoff note template

```
## Handoff (Agent-03)
- Contracts locked: Movie/Series/Season/Episode DTO shapes (see agents/agent-03-catalog.md)
- WatchProgressRepository scaffolded (no service logic) — Agent-05 fills it.
- Open follow-ups: search endpoint (deferred), public cache (deferred)
```
