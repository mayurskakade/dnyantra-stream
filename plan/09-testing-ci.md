# 09 — Testing Expansion + CI + Tooling (W11)

**Blocks**: M6 release.
**Depends on**: each prior workstream contributes its own tests; this workstream adds the cross-cutting coverage, lint configs, and CI workflow.

## Deliverables

### 1. Backend unit tests (gaps)

- Complete `AccessPolicyServiceTest`: archived state, share-link expired, share-link exhausted (`uses >= max_uses`), public window edges (before start, after end).
- New: `CloudflareStreamServiceTest` (in W5).
- New: `R2ServiceTest` (in W5).
- New: `PlaybackServiceTest` (in W6).
- New: `WatchProgressServiceTest` DB-backed (in W6 — supplements existing in-memory tests).
- New: `AdminAuditServiceTest` — verifies row insertion with JSON diffs.
- New: `CsrfServiceTest` — generation, validation, constant-time check.
- New: `ValidatorTest` — each rule, especially enums.

### 2. Backend feature tests

Use the existing docker-compose MySQL on a **separate database name** (e.g. `dnyantra_test`). Reset between tests.

- `tests/Feature/AuthEndpointsTest.php` — login happy path, 401 wrong password, refresh rotation, refresh reuse rejection, rate limit 429.
- `tests/Feature/CatalogEndpointsTest.php` — auth catalog filters by access policy; public catalog filters by `is_listed_publicly` + window; transformers strip sensitive keys.
- `tests/Feature/PlaybackEndpointsTest.php` (also created in W6).
- `tests/Feature/WatchProgressEndpointsTest.php` — upsert, completion threshold, clear.
- `tests/Feature/AdminGuardsTest.php` (also in W8).
- `tests/Feature/CsrfCoverageTest.php` (also in W8).
- `tests/Feature/ResponseShapeTest.php` (in W8).

### 3. Mobile tests

- Model `fromJson` tests for every DTO in `catalog_models.dart`, `home_models.dart`, `playback_session_model.dart`.
- Widget tests: login (exists), home loaded/empty/error, catalog list, movie detail, player loading state.
- `ProviderScope` overrides + a mocked Dio for repository-layer isolation.

### 4. Lint configs

- `/.editorconfig` at repo root: LF line endings, 4-space PHP, 2-space Dart, trim trailing whitespace, final newline.
- `backend/.php-cs-fixer.php`: PSR-12 base + strict types declaration enforcement.
- `mobile/analysis_options.yaml`: extend `package:flutter_lints/flutter.yaml` + opt-in `prefer_relative_imports`, `unawaited_futures`, `avoid_print`.

### 5. CI workflow

`.github/workflows/ci.yml` with three jobs (all gating PR merge):

**Job `backend`**:
- PHP 8.3, `composer install`, spin up MySQL 8 service + Redis service, `composer migrate` against test DB, `composer test`, `vendor/bin/php-cs-fixer fix --dry-run --diff`.

**Job `mobile`**:
- Flutter stable, `flutter pub get`, `flutter analyze`, `flutter test`.

**Job `security`**:
- Fail if `git ls-files | grep -E '(^|/)\.env$'` returns anything.
- Grep PR diff for common secret patterns (warn / fail at owner's discretion).
- Grep PR diff for new `->query(` / `->exec(` outside `tests/`.

### 6. Composer scripts

- `composer test` → `vendor/bin/phpunit`.
- `composer test:feature` → `vendor/bin/phpunit --testsuite=Feature`.
- `composer lint` → `vendor/bin/php-cs-fixer fix --dry-run --diff`.
- `composer fix` → `vendor/bin/php-cs-fixer fix`.

## Files

**Create**: `.editorconfig`, `backend/.php-cs-fixer.php`, `.github/workflows/ci.yml`, the test files listed above.

**Modify**: `backend/composer.json` (scripts), `mobile/analysis_options.yaml`, `mobile/pubspec.yaml` (dev deps if needed).

## Acceptance

- `composer test` runs unit + feature tests; all green.
- `composer lint` returns 0.
- `flutter analyze` returns 0.
- `flutter test` returns 0.
- CI workflow runs all three jobs on every PR; merge is blocked unless all three are green.

## Out of scope

- E2E browser automation (Playwright/Cypress) — manual smoke per §10 covers it for now.
- Mutation testing, coverage thresholds — add later if useful.
