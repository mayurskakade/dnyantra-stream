# Agent-06 — Admin Portal

**Phase**: P3 (parallel with Agent-05, Agent-07)
**Workstream**: W7 — see [`../plan/06-admin-portal.md`](../plan/06-admin-portal.md)
**PR**: PR-F
**Branch**: `agent-06-admin-portal`

## Role

Build the server-rendered admin portal: views, CRUD controllers for every catalog entity, media management (direct upload + sync + delete), access management, audit log index, and server-enforced rights guards.

## Depends on

- **Agent-02 merged**: `SessionService`, `CsrfService`, `AdminSessionMiddleware`, `CsrfMiddleware`, `AdminAuthController` stub.
- **Agent-03 merged**: catalog repositories.
- **Agent-04 merged**: `CloudflareStreamService` direct-upload URL, `R2Service` presign.

## Owned files (you create)

```
backend/app/Views/layouts/admin.php
backend/app/Views/partials/{nav,flash,pagination,csrf}.php
backend/app/Views/auth/login.php
backend/app/Views/dashboard/index.php
backend/app/Views/movies/{index,create,edit}.php
backend/app/Views/series/{index,create,edit}.php
backend/app/Views/seasons/{index,create,edit}.php
backend/app/Views/episodes/{index,create,edit}.php
backend/app/Views/categories/{index,create,edit}.php
backend/app/Views/genres/{index,create,edit}.php
backend/app/Views/users/{index,create,edit}.php
backend/app/Views/access/index.php
backend/app/Views/audit_logs/index.php
backend/app/Support/View.php

backend/app/Controllers/Admin/DashboardController.php
backend/app/Controllers/Admin/MoviesController.php
backend/app/Controllers/Admin/SeriesController.php
backend/app/Controllers/Admin/SeasonsController.php
backend/app/Controllers/Admin/EpisodesController.php
backend/app/Controllers/Admin/CategoriesController.php
backend/app/Controllers/Admin/GenresController.php
backend/app/Controllers/Admin/UsersController.php
backend/app/Controllers/Admin/MediaController.php
backend/app/Controllers/Admin/AccessController.php
backend/app/Controllers/Admin/AuditLogController.php

backend/app/Repositories/AdminAuditLogRepository.php
backend/app/Repositories/MediaAssetRepository.php

backend/tests/Feature/AdminGuardsTest.php
backend/tests/Feature/AdminCrudTest.php
```

## Shared files (you modify, additive only)

```
backend/app/Services/AdminAuditService.php   — implement record() (currently stub)
backend/routes/admin.php                     — full admin route table
backend/public/admin.php                     — mount AdminSessionMiddleware + CsrfMiddleware globally
```

## Forbidden files

- Anything under `app/Controllers/Api/` (Agent-03, Agent-05 own these).
- `PlaybackService`, `WatchProgressService` (Agent-05).
- `CloudflareStreamService`, `R2Service` implementations (Agent-04 — only call them).
- `mobile/` (Agent-07, Agent-08).

## Inputs consumed

- From Agent-02: `SessionService`, `CsrfService`, `AdminSessionMiddleware`, `CsrfMiddleware`, `csrf_field()` helper convention.
- From Agent-03: catalog repositories (`MovieRepository`, `SeriesRepository`, etc.), transformers (admin views may use richer non-public shapes — go via repos directly, not public transformers).
- From Agent-04: `CloudflareStreamService::createDirectUploadUrl`, `getVideoStatus`, `deleteVideo`; `R2Service::createPresignedUploadUrl` for posters/banners.

## Outputs produced

### Rights guard (server-enforced)
In every `update`/`store` for Movies, Series, Episodes:
- If incoming `visibility=public`:
  - Require post-update `rights_status ∈ {owned_by_me, licensed_public, public_domain, creative_commons}`.
  - Require `public_streaming_enabled=true`.
  - Else respond 422 with field-level error; no DB write.
- Always call `AdminAuditService::record()` with `before` / `after` diffs before commit.

### Audit log row shape
```php
['admin_user_id' => int, 'action' => string, 'entity_type' => string, 'entity_id' => ?int,
 'before_json' => ?string, 'after_json' => ?string, 'ip' => string, 'user_agent' => string,
 'created_at' => datetime]
```

### View convention
- Every form embeds `<?= csrf_field() ?>`.
- Every POST route runs `CsrfMiddleware`.

## Acceptance criteria

- [ ] `/admin/login` → log in as `admin@example.com / ChangeMe123!` → lands on dashboard with counts.
- [ ] Admin creates a movie; defaults are `visibility=private`, `rights_status=personal_only`.
- [ ] Admin sets `visibility=public` on `personal_only` movie → 422 with explanatory field error; no DB write.
- [ ] Admin sets `rights_status=owned_by_me` + `public_streaming_enabled=true` + `visibility=public` → 200; audit log row appears with `before`/`after` JSON.
- [ ] Admin POST without CSRF token → 419.
- [ ] Admin requests `POST /admin/media/upload-url` → response includes Stream `upload_url` + `uid`; saved as `media_asset` row.
- [ ] `/admin/audit-logs?entity_type=movie` filters correctly and paginates.

## Handoff note template

```
## Handoff (Agent-06)
- Contracts locked: AdminAuditService::record() signature
- All admin write paths trigger audit log writes (Agent-09 will verify in security sweep)
- Open follow-ups: bulk import, role granularity, theming — all deferred
```
