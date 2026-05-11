# 06 — Admin Portal (W7)

**Blocks**: M4 milestone (admin-operable platform).
**Depends on**: W1 (validator, error handler), W2 (sessions, CSRF), W4 (catalog repos), W5 (Stream upload URL, R2 presign).

## Why

The current `/admin/login` is a stub. Admins have no way to create content, set visibility under rights guards, manage assignments, or audit changes — all of which are spec requirements.

## Deliverables

### 1. View layer (plain PHP, no framework)

Under `backend/app/Views/`:

- `layouts/admin.php` — base layout (nav, flash slot, CSRF helper).
- `partials/nav.php`, `partials/flash.php`, `partials/pagination.php`, `partials/csrf.php`.
- `auth/login.php`.
- `dashboard/index.php`.
- `movies/{index,create,edit}.php`, `series/{...}.php`, `seasons/{...}.php`, `episodes/{...}.php`, `categories/{...}.php`, `genres/{...}.php`, `users/{...}.php`, `access/index.php`, `audit_logs/index.php`.

Helpers: a tiny `app/Support/View.php` with `render(string $template, array $data): string` and `csrf_field()`, `e()` (htmlspecialchars).

### 2. Controllers

Under `backend/app/Controllers/Admin/`:

- `DashboardController` — counts: users, movies, series, episodes, sessions today.
- `MoviesController`, `SeriesController`, `SeasonsController`, `EpisodesController`, `CategoriesController`, `GenresController`, `UsersController` — full `index/create/store/edit/update/delete`.
- `MediaController` — `requestUploadUrl` (calls `CloudflareStreamService::createDirectUploadUrl`), `syncStatus` (calls `getVideoStatus`), `deleteAsset`.
- `AccessController` — `user_content_access` assign/revoke; visibility mutation; share-link create/revoke.
- `AuditLogController` — index with filters (admin user, entity type, date range, action).

### 3. Visibility / rights guard (server-enforced)

In `MoviesController::update`, `SeriesController::update`, `EpisodesController::update`:

- Before persisting, if incoming `visibility = "public"`:
  - Require `rights_status ∈ {owned_by_me, licensed_public, public_domain, creative_commons}` (post-update value).
  - Require `public_streaming_enabled = true`.
  - Otherwise reject with `422` + form error. **Server-side, not UI-only.**
- Always write audit log diff via `AdminAuditService::record(...)` before commit.

### 4. Audit log

- Implement `backend/app/Services/AdminAuditService::record(int $adminId, string $action, string $entityType, ?int $entityId, ?array $before, ?array $after, Request $request): void`:
  - Inserts into `admin_audit_logs` with JSON-encoded `before` / `after` and request metadata (IP, UA).
- Call from every admin write path (create/update/delete + visibility mutations + media operations + access assign/revoke + share-link create/revoke).

### 5. CSRF everywhere

- Every form embeds `csrf_field()`.
- Every admin POST route runs `CsrfMiddleware`.

### 6. Routes

Replace stub `backend/routes/admin.php` with the full table (full list in [10-pr-sequence-and-risks.md](10-pr-sequence-and-risks.md) §API checklist).

## Files

**Create**:
- `app/Views/**/*.php` (≈25 templates)
- `app/Controllers/Admin/{Dashboard,Movies,Series,Seasons,Episodes,Categories,Genres,Users,Media,Access,AuditLog}Controller.php`
- `app/Support/View.php`

**Modify**:
- `app/Services/AdminAuditService.php`
- `routes/admin.php`
- `public/admin.php` (mount session + CSRF middleware globally for non-login routes)

## Acceptance

- Admin can log in at `/admin/login`, lands on `/admin/dashboard` showing counts.
- Admin creates a movie (defaults `visibility=private`, `rights_status=personal_only`).
- Admin tries to set `visibility=public` on a `rights_status=personal_only` movie → form returns `422` with explanatory error, no DB write.
- Admin flips `rights_status` to `owned_by_me`, `public_streaming_enabled=true`, then sets `visibility=public` → accepted; audit log row inserted with `before`/`after` diff.
- Admin POST without CSRF token → `419`/`403` HTML error.
- Admin requests a Stream upload URL → response shows the URL and `uid`; persisting the uid attaches it as a `media_asset`.
- `/admin/audit-logs?entity_type=movie` filters correctly and paginates.

## Out of scope

- Bulk imports / CSV upload.
- Role granularity beyond `admin` / `viewer`.
- Theming, dark mode, fancy UI — minimal styled HTML is fine.
