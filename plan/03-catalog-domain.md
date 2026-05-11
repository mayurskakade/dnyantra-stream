# 03 — Catalog Domain (W4)

**Blocks**: W9 (mobile catalog), W6 (playback resolves content via these repos).
**Depends on**: W1 (route params, error handler, validator).

## Why

Catalog endpoints are stubs (501). Mobile and public clients cannot list or detail any content. This workstream introduces repositories, public-safe DTO transformers, and the full read-side endpoint surface for auth and public consumers.

## Deliverables

### 1. Repositories (PDO prepared statements only)

Under `backend/app/Repositories/`:

- `CategoryRepository`, `GenreRepository`
- `MovieRepository` — list by filters, get by slug, eager-load category/genre joins as needed.
- `SeriesRepository`, `SeasonRepository`, `EpisodeRepository`
- `WatchProgressRepository` — used in W3/W6, scaffold here.
- `UserContentAccessRepository`, `ShareLinkRepository` — used by `AccessPolicyService` already; ensure repos exist with `findActiveForUser`, `findByTokenHash`.

**Rules**:
- Every query is `prepare()` + `execute()`. Zero string interpolation of user input.
- Repos return associative arrays or row DTOs; transformation to public shape happens in transformers.

### 2. Service layer

- Fill out `backend/app/Services/CatalogService.php` (currently empty stub):
  - `home(?User $user): array` — featured rails + continue-watching.
  - `listMovies(array $filters, int $page): array`
  - `getMovie(string $slug, ?User $user): array`
  - `listSeries(array $filters, int $page): array`
  - `getSeries(string $slug, ?User $user): array`
  - `listSeasons(int $seriesId): array`
  - `listEpisodes(int $seasonId): array`
  - `continueWatching(User $user): array`
- Public variants additionally filter by `is_listed_publicly = 1` AND `AccessPolicyService::isPublicAllowed(...)`.

### 3. Transformers

New directory `backend/app/Http/Transformers/`:

- `MovieTransformer`, `SeriesTransformer`, `SeasonTransformer`, `EpisodeTransformer`, `MediaAssetTransformer`, `CategoryTransformer`, `GenreTransformer`.
- **Never** include in public output: `provider_uid`, raw `storage_key`, signing tokens, `rights_status`, `public_streaming_enabled`, internal flags.
- Authenticated admin endpoints may receive richer payloads — but those live in W7 (admin portal), not here.

### 4. Routes + controllers

New `backend/app/Controllers/Api/CatalogController.php`. New file `backend/routes/public.php`.

| Method | Path | Auth | Handler |
|---|---|---|---|
| GET | /api/home | bearer | `CatalogController@home` |
| GET | /api/categories | bearer | `CatalogController@listCategories` |
| GET | /api/genres | bearer | `CatalogController@listGenres` |
| GET | /api/movies | bearer | `CatalogController@listMovies` (paginated) |
| GET | /api/movies/{slug} | bearer | `CatalogController@showMovie` |
| GET | /api/series | bearer | `CatalogController@listSeries` |
| GET | /api/series/{slug} | bearer | `CatalogController@showSeries` |
| GET | /api/seasons/{id}/episodes | bearer | `CatalogController@listEpisodes` |
| GET | /api/continue-watching | bearer | `CatalogController@continueWatching` |
| GET | /public/catalog | none + rate-limit | `CatalogController@publicCatalog` |
| GET | /public/movies/{slug} | none | `CatalogController@publicMovie` |
| GET | /public/series/{slug} | none | `CatalogController@publicSeries` |

### 5. Access policy wiring

Every catalog endpoint (auth and public) consults `AccessPolicyService` before returning detail data. Add ergonomic helpers if useful:

- `AccessPolicyService::filterListedForUser(array $rows, ?User $user): array`
- `AccessPolicyService::assertCanView(?User $user, string $type, int $id): void`

## Files

**Create**: `app/Repositories/*Repository.php` (≈10 files), `app/Http/Transformers/*Transformer.php` (≈7 files), `app/Controllers/Api/CatalogController.php`, `routes/public.php`.

**Modify**: `app/Services/CatalogService.php`, `app/Services/AccessPolicyService.php` (add helpers), `routes/api.php`, `public/index.php` (mount `routes/public.php`).

## Acceptance

- `GET /api/movies` returns paginated movies for the authenticated user, filtered by access policy.
- `GET /api/movies/{slug}` returns 404 for a private movie the user lacks assignment for (not 403 — avoid existence leak).
- `GET /public/catalog` returns only `visibility=public AND is_listed_publicly=1` movies within their public window.
- No public response payload contains `provider_uid`, `rights_status`, `storage_key`, or `public_streaming_enabled`.
- Continue-watching shows incomplete progress rows sorted by `last_played_at` desc.

## Out of scope

- Search endpoint (full-text). Defer until requested.
- Caching layer (public catalog cache). Add only if traffic warrants.
- Admin-side CRUD — covered in W7.
