# 00 — Coordination Protocol

Read this first if you are an agent on this project. Every other agent doc assumes you follow these rules.

## 1. Branching

- Each agent works on its own branch: `agent-<NN>-<short-name>` (e.g. `agent-03-catalog`).
- Base branch: `main` after the previous phase has merged.
- Open a draft PR immediately when the branch is created so other agents can see in-flight work.
- One PR per agent. No multi-agent shared branches.

## 2. File ownership

Every agent doc lists three categories:

- **Owned files** — only this agent creates them in its phase. No other agent in the same phase touches these paths. Safe to create freely.
- **Shared files (modify only)** — pre-existing files that multiple agents may need to edit. Edits MUST be **append-only** in the same phase. Coordinate via interface contracts (§4) when changing semantics.
- **Forbidden files** — paths that belong to a different agent. Do not read for inspiration even if tempting.

If you find yourself needing to edit a forbidden file, stop and post on the PR thread to negotiate scope before proceeding.

## 3. Shared-file edit protocol

For shared files (`routes/api.php`, `routes/admin.php`, `public/index.php`, `public/admin.php`, `composer.json`, `mobile/pubspec.yaml`):

- **Add new entries, never reformat existing ones.**
- Keep edits minimal and localized.
- If two agents in the same phase both must modify the same shared file, the later-merging agent rebases and resolves; conflicts are expected and OK as long as both edits are additive.
- Routes files: add your routes in a clearly commented block at the bottom of the file (e.g. `// --- Agent 03: catalog ---`).

## 4. Interface contracts

When Agent X produces something that Agent Y consumes (cross-phase or cross-agent), the contract is locked **before** Agent Y starts coding against it.

- Each consumer documents the contract it expects in its own agent doc under "Inputs / interfaces consumed".
- Each producer documents the contract it ships in its own agent doc under "Outputs / interfaces produced".
- Mismatch → the **producer** is authoritative until P5; if the consumer needs a different shape, the producer issues a typed change in a follow-up commit and tags both PRs.

Examples of locked contracts in this project:

- API response shape for catalog endpoints — produced by Agent-03, consumed by Agent-07.
- `POST /api/playback-sessions` request/response — produced by Agent-05, consumed by Agent-08.
- `RateLimiter` interface — produced by Agent-01, consumed by Agent-02, Agent-05.
- `CloudflareStreamService::createSignedPlaybackToken` signature — produced by Agent-04, consumed by Agent-05.

## 5. Migration discipline

- Migrations are **forward-only**.
- Agents in the same phase coordinate migration numbers ahead of time. Reserved numbers in this plan:
  - `003_admin_2fa.sql` — Agent-02
  - `004_watch_progress_unique.sql` — Agent-05 (only if needed)
  - `005_*` and up — reserved for follow-ups; ask before claiming.
- Never edit an applied migration. Never re-number an existing one.

## 6. Test discipline

- Every agent ships unit tests for its services and feature tests for its endpoints.
- Tests live next to the workstream they cover. Agent-10 only adds cross-cutting tests + CI.
- Run `composer test` and (for mobile agents) `flutter test` locally before opening the PR for review.

## 7. Handoff artifacts

When an agent finishes, it produces a short handoff note in the PR description:

```
## Handoff
- Contract changes: <list any interface shape changes>
- New env vars: <list>
- New migrations: <list>
- Open follow-ups: <list of `TODO(agent-NN)` markers left in code, if any>
```

The next phase's agents read these handoffs before they start.

## 8. Conflict resolution

- If two agents disagree on an interface shape, the agent **earlier in the dependency chain** wins. If still ambiguous, the human reviewer breaks the tie.
- If two agents both edit a route table and conflict on merge: the later-merging agent rebases. Routes are commutative — order shouldn't matter for the framework.

## 9. Scope discipline

- An agent does only its workstream. No drive-by refactors.
- If you spot a bug outside scope: leave a `TODO(agent-NN-followup)` comment and mention it in the PR handoff. Do not fix.
- If you spot a bug **inside** scope: fix it and call it out.

## 10. Done means done

An agent's task is not complete until:

1. All "Owned files" exist and are wired in.
2. All "Acceptance criteria" in the agent doc are demonstrably met.
3. Tests pass locally (`composer test`, `flutter test` as relevant).
4. PR description includes the Handoff block (§7).
5. PR is in **Ready for review** state (no longer draft).
