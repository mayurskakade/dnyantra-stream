# Dnyantra Stream — End-to-End Development Plan

This directory is the single source of truth for sequencing remaining work to ship the private-first streaming platform. Each file is self-contained: the *why*, the deliverables, the files touched, and the acceptance criteria.

Read order:

1. [00-overview.md](00-overview.md) — current state, milestones, dependency graph
2. [01-backend-core-hardening.md](01-backend-core-hardening.md) — W1 (blocks everything)
3. [02-auth-completion.md](02-auth-completion.md) — W2
4. [03-catalog-domain.md](03-catalog-domain.md) — W4
5. [04-cloudflare-integration.md](04-cloudflare-integration.md) — W5 (Stream + R2)
6. [05-playback-and-progress.md](05-playback-and-progress.md) — W3 + W6
7. [06-admin-portal.md](06-admin-portal.md) — W7
8. [07-mobile-app.md](07-mobile-app.md) — W9 + W10
9. [08-security-hardening.md](08-security-hardening.md) — W8
10. [09-testing-ci.md](09-testing-ci.md) — W11
11. [10-pr-sequence-and-risks.md](10-pr-sequence-and-risks.md) — PR plan, risks, E2E verification

## Conventions used in these docs

- **Files to create** vs **Files to modify** — explicit paths under `backend/` or `mobile/`.
- **Acceptance** — what must pass before the workstream is considered done.
- **Depends on** — workstreams or PRs that must merge first.
- **Out of scope** — items deliberately deferred.

## Guiding constraints (repeated from `AGENTS.md` — non-negotiable)

- Default new content to `visibility=private`, `rights_status=personal_only`.
- Never issue permanent public playback URLs.
- Every playback flow goes through backend-issued, short-lived signed sessions.
- Mobile app never holds Cloudflare API tokens.
- Share / refresh / playback session tokens are stored **hashed**.
