# Phases — Dependency Gating

Five phases. A phase begins only after every PR in the prior phase is **merged to `main`**.

```
┌─────────────────────────────────────────────────────────────────┐
│ P1: Foundation  (sequential, 1 agent)                           │
│   Agent-01 Core Hardening                                       │
└──────────────┬──────────────────────────────────────────────────┘
               │ unblocks: route params, error handler, validator,
               │           CORS, RateLimiter, config boot validation
               ▼
┌─────────────────────────────────────────────────────────────────┐
│ P2: Parallel Build  (3 agents concurrent)                       │
│   Agent-02 Auth     ◄── consumes RateLimiter, ErrorHandler      │
│   Agent-03 Catalog  ◄── consumes Router params, Validator       │
│   Agent-04 CF       ◄── consumes ErrorHandler, ConfigException  │
└──────────────┬──────────────────────────────────────────────────┘
               │ unblocks: catalog API contract,
               │           Stream signing fn, R2 presign fn,
               │           admin session middleware
               ▼
┌─────────────────────────────────────────────────────────────────┐
│ P3: Integration  (3 agents concurrent)                          │
│   Agent-05 Playback ◄── consumes catalog repos + Stream signing │
│   Agent-06 Admin    ◄── consumes auth sessions + catalog + CF   │
│   Agent-07 Mobile   ◄── consumes catalog API contract           │
│            Catalog                                              │
└──────────────┬──────────────────────────────────────────────────┘
               │ unblocks: playback session API,
               │           mobile catalog screens
               ▼
┌─────────────────────────────────────────────────────────────────┐
│ P4: Mobile Player  (1 agent)                                    │
│   Agent-08 Mobile Player                                        │
│     ◄── consumes Agent-05 (playback API) + Agent-07 (catalog UI)│
└──────────────┬──────────────────────────────────────────────────┘
               │ unblocks: full app E2E
               ▼
┌─────────────────────────────────────────────────────────────────┐
│ P5: Release Gate  (2 agents concurrent)                         │
│   Agent-09 Security Sweep                                       │
│   Agent-10 Testing + CI                                         │
└─────────────────────────────────────────────────────────────────┘
```

## Phase gate criteria

Each gate is a checklist. The phase opens when every box is checked.

### Gate P1 → P2

- [ ] Agent-01 PR merged.
- [ ] `Router` supports `{param}` segments (smoke-checked against a dummy route).
- [ ] `ErrorHandler` returns structured JSON for `/api/*` and HTML for `/admin/*`.
- [ ] `Validator` available with enum support.
- [ ] `Config::bootValidate(...)` aborts boot on missing required env.
- [ ] `CorsMiddleware` mounted on `/api/*` and `/public/*`.
- [ ] `RateLimiter` interface + Redis + InMemory implementations exist.
- [ ] All Agent-01 unit tests green.

### Gate P2 → P3

- [ ] Agent-02 PR merged: refresh-token rotation live; admin session+CSRF working; login rate-limited.
- [ ] Agent-03 PR merged: every catalog endpoint listed in [`../plan/03-catalog-domain.md`](../plan/03-catalog-domain.md) returns real data; transformers strip sensitive keys.
- [ ] Agent-04 PR merged: `CloudflareStreamService::createSignedPlaybackToken` produces verifying RS256 JWTs; `R2Service` presigns with whitelists.
- [ ] No regression in Agent-01 acceptance tests.

### Gate P3 → P4

- [ ] Agent-05 PR merged: `POST /api/playback-sessions` returns signed URL; watch-progress upsert endpoint working.
- [ ] Agent-06 PR merged: admin can CRUD catalog, rights guard rejects invalid public flips, audit log writes on every mutation.
- [ ] Agent-07 PR merged: mobile catalog browses and shows movie/series detail; UI states implemented.
- [ ] No regression in P2 acceptance tests.

### Gate P4 → P5

- [ ] Agent-08 PR merged: mobile player plays HLS via signed session, syncs progress, resumes incomplete content.
- [ ] E2E smoke (plan §10) passes manually.

### Release gate (P5 done)

- [ ] Agent-09 PR merged: §14 checklist sweep complete; `LogRedactor` in place; secret-scan grep green.
- [ ] Agent-10 PR merged: full CI workflow gating PRs; backend + mobile + security jobs green.
- [ ] All tests green in CI.

## What if a phase blocks?

- If an agent's PR cannot merge (failing tests, design issue), do not start the next phase for any agent dependent on it.
- Other parallel agents in the same phase can still merge if their PRs are independently complete.
- If a contract change in P2 invalidates P3 work, the P3 agent rebases. Cost of one rebase < cost of stale contracts.
