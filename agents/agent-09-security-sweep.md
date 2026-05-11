# Agent-09 — Security Hardening Sweep

**Phase**: P5 (parallel with Agent-10)
**Workstream**: W8 — see [`../plan/08-security-hardening.md`](../plan/08-security-hardening.md)
**PR**: PR-I (split with Agent-10) — or own PR-I.1
**Branch**: `agent-09-security-sweep`

## Role

Final cross-cutting security pass. Verify §14 checklist, add `LogRedactor`, add response-shape leak tests, add admin guard verification tests, audit token hashing, audit SQL safety.

## Depends on

- **All P1–P4 agents merged**. This is a verification + hardening pass; it must run on the integrated codebase.

## Owned files (you create)

```
backend/app/Support/LogRedactor.php
backend/tests/Feature/ResponseShapeTest.php
backend/tests/Feature/AdminGuardsTest.php        (if Agent-06 left gaps — extend it)
backend/tests/Feature/CsrfCoverageTest.php
backend/tests/Feature/RateLimitCoverageTest.php
backend/tests/Unit/LogRedactorTest.php
backend/scripts/audit-sql.sh                     (grep guard for ->query( / ->exec( in non-test code)
backend/scripts/audit-secrets.sh                 (grep guard for accidental committed secrets)
```

## Shared files (you modify, additive only)

```
backend/app/Core/ErrorHandler.php   — pipe logged context through LogRedactor
backend/.env.example                — audit placeholders, no real keys
```

You may add small fixes if you find security holes — but document them clearly in the PR. Do not refactor unrelated code.

## Forbidden files

- Mobile (`mobile/`) — security pass is backend-focused; Flutter has minimal attack surface and Agent-08 already shipped it.
- Anything that would re-open an interface contract from earlier agents.

## Inputs consumed

The integrated codebase from P1–P4. Every prior agent doc's acceptance criteria.

## Outputs produced

### `LogRedactor::redact(array $context): array`
Scrubs case-insensitive keys: `password`, `password_confirmation`, `authorization`, `cookie`, `access_token`, `refresh_token`, `token`, `token_hash`, `session_token`, `csrf`. Recurses into nested arrays.

### `ResponseShapeTest`
Hits every public + auth catalog/playback endpoint with a fixture user + content, asserts none of: `provider_uid`, `storage_key`, `rights_status`, `public_streaming_enabled`, `session_token_hash`, `token_hash` appear in response body.

### `CsrfCoverageTest`
For each admin write route, asserts 419/403 when CSRF token is omitted/wrong.

### `RateLimitCoverageTest`
Confirms 429 on:
- `POST /api/auth/login` (6 hits / 60s)
- `POST /api/auth/refresh` (31 hits / 60s)
- `POST /public/playback-sessions` (21 hits / 5min)

### SQL audit script
Fails if `grep -rE '->query\(|->exec\(' backend/app backend/routes` finds any hit. Test fixtures and migrations are excluded.

### Token hashing audit (verification only — no code changes expected)
Confirm these are stored hashed and read with `hash_equals`:
- `refresh_tokens.token_hash` (Agent-02)
- `share_links.token_hash` (Agent-03 / existing)
- `playback_sessions.session_token_hash` (Agent-05)

## Acceptance criteria

- [ ] `ResponseShapeTest` green — no sensitive keys leaked.
- [ ] `CsrfCoverageTest` green — every admin POST requires CSRF.
- [ ] `RateLimitCoverageTest` green.
- [ ] `audit-sql.sh` finds zero offenders.
- [ ] `audit-secrets.sh` finds zero offenders in working tree.
- [ ] `LogRedactor` scrubs all listed keys including nested; `LogRedactorTest` green.
- [ ] `ErrorHandler` logged context routes through `LogRedactor`.
- [ ] §14 checklist (in [`../plan/08-security-hardening.md`](../plan/08-security-hardening.md)) all items verified.

## Handoff note template

```
## Handoff (Agent-09)
- New: app/Support/LogRedactor.php — used by ErrorHandler
- Security holes found and fixed: <list, or "none">
- §14 checklist: complete
- Open follow-ups: external pentest, WAF — out of scope
```
