# Agent-10 — Testing Expansion + CI + Tooling

**Phase**: P5 (parallel with Agent-09)
**Workstream**: W11 — see [`../plan/09-testing-ci.md`](../plan/09-testing-ci.md)
**PR**: PR-I (split with Agent-09) — or own PR-I.2
**Branch**: `agent-10-testing-ci`

## Role

Add cross-cutting tests that no prior agent owned, ship the lint configs, and stand up the GitHub Actions workflow that gates every future PR.

## Depends on

- **All P1–P4 agents merged**.
- Can run concurrently with Agent-09 (disjoint file sets), but if Agent-09's `LogRedactor` is required by a test you write, sync via PR thread.

## Owned files (you create)

```
.editorconfig
.github/workflows/ci.yml
backend/.php-cs-fixer.php

backend/tests/Unit/ValidatorTest.php                  (if Agent-01 left gaps)
backend/tests/Unit/AdminAuditServiceTest.php
backend/tests/Feature/AuthEndpointsTest.php
backend/tests/Feature/CatalogEndpointsTest.php        (if Agent-03 left gaps)
backend/tests/Feature/WatchProgressEndpointsTest.php  (if Agent-05 left gaps)
backend/tests/Feature/E2ESmokeTest.php

mobile/test/widget_test_helpers.dart                  (ProviderScope + mocked Dio)
mobile/test/features/auth/login_screen_test.dart      (if not already shipped)
```

## Shared files (you modify, additive only)

```
backend/composer.json                — add scripts (test, test:feature, lint, fix)
mobile/analysis_options.yaml         — extend with prefer_relative_imports, unawaited_futures, avoid_print
```

You may extend existing test files if you find gaps. Coordinate via PR thread if your change touches an Agent-09-owned file.

## Forbidden files

- Production source under `backend/app/` and `mobile/lib/` — you only add tests + configs, you do not patch product code.
- If a test fails due to a real bug, file it as a follow-up and skip the test with a `markTestSkipped('TODO(agent-10-followup): ...')`. Do not fix.

## Inputs consumed

The full integrated codebase from P1–P4. Every prior agent's "Acceptance criteria" block is a test idea source.

## Outputs produced

### `.editorconfig` (repo root)
- LF line endings, final newline, trim trailing whitespace.
- 4-space indent for PHP, 2-space for Dart/YAML/JSON.

### `backend/.php-cs-fixer.php`
- PSR-12 base.
- `declare_strict_types` required.

### `mobile/analysis_options.yaml`
- Extends `package:flutter_lints/flutter.yaml`.
- Opts in: `prefer_relative_imports`, `unawaited_futures`, `avoid_print`.

### `.github/workflows/ci.yml`
Three jobs, all required:
- **backend**: PHP 8.3 + MySQL 8 service + Redis service → `composer install` → `composer migrate` (test DB) → `composer test` → `composer lint`.
- **mobile**: Flutter stable → `flutter pub get` → `flutter analyze` → `flutter test`.
- **security**:
  - Fail if `git ls-files | grep -E '(^|/)\.env$'` matches.
  - Run `backend/scripts/audit-sql.sh` (provided by Agent-09).
  - Run `backend/scripts/audit-secrets.sh` (provided by Agent-09).

### `composer.json` scripts
```json
"scripts": {
  "test": "phpunit",
  "test:feature": "phpunit --testsuite=Feature",
  "lint": "php-cs-fixer fix --dry-run --diff",
  "fix": "php-cs-fixer fix",
  "migrate": "php scripts/migrate.php"
}
```

### `E2ESmokeTest` (feature-level)
A single PHPUnit test that boots the test DB, seeds an admin + a sample movie, logs in via API, hits each major endpoint, opens a playback session, posts a progress heartbeat — single pass over the happy path.

## Acceptance criteria

- [ ] `composer test` runs all unit + feature tests; green.
- [ ] `composer lint` returns 0.
- [ ] `flutter analyze` returns 0.
- [ ] `flutter test` returns 0.
- [ ] CI workflow runs all three jobs on a real PR; merge blocked unless all three green.
- [ ] `.editorconfig` applies to a fresh editor session (verify with a sample file).
- [ ] `E2ESmokeTest` passes against the docker-compose MySQL + Redis stack.

## Handoff note template

```
## Handoff (Agent-10)
- CI: .github/workflows/ci.yml — three required jobs (backend, mobile, security)
- Composer scripts: test, test:feature, lint, fix, migrate
- Test gaps filled: <list>
- Test failures discovered (skipped with TODO): <list, or "none">
- Open follow-ups: coverage thresholds, mutation testing — out of scope
```
