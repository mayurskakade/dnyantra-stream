# Parallel AI Agent Plan — Dnyantra Stream

Ten agents, five phases. Each agent owns a workstream from [`../plan/`](../plan/). File ownership is explicit so agents can run in parallel without stepping on each other.

## How to use this directory

1. Read [`00-coordination.md`](00-coordination.md) first — branching, file-ownership rules, interface-contract protocol, conflict resolution.
2. Read [`phases.md`](phases.md) — the dependency-ordered phases and what gates progression between them.
3. For each running agent, hand it its own file (e.g. `agent-03-catalog.md`) plus `00-coordination.md` plus its referenced [`../plan/`](../plan/) doc.

## Agents

| # | Agent | Phase | Workstream | PR |
|---|---|---|---|---|
| 01 | [Core Hardening](agent-01-core-hardening.md) | P1 | W1 | PR-A |
| 02 | [Auth Completion](agent-02-auth.md) | P2 | W2 | PR-B |
| 03 | [Catalog Domain](agent-03-catalog.md) | P2 | W4 | PR-C |
| 04 | [Cloudflare](agent-04-cloudflare.md) | P2 | W5 | PR-D |
| 05 | [Playback + Progress](agent-05-playback-progress.md) | P3 | W3 + W6 | PR-E |
| 06 | [Admin Portal](agent-06-admin-portal.md) | P3 | W7 | PR-F |
| 07 | [Mobile Catalog](agent-07-mobile-catalog.md) | P3 | W9 | PR-G |
| 08 | [Mobile Player](agent-08-mobile-player.md) | P4 | W10 | PR-H |
| 09 | [Security Sweep](agent-09-security-sweep.md) | P5 | W8 | PR-I (part) |
| 10 | [Testing + CI](agent-10-testing-ci.md) | P5 | W11 | PR-I (part) |

## Phase summary

- **P1 (sequential)**: Agent-01 only. Lands the foundation.
- **P2 (3-way parallel)**: Agent-02, Agent-03, Agent-04 — disjoint file sets.
- **P3 (3-way parallel)**: Agent-05, Agent-06, Agent-07 — disjoint after P2.
- **P4 (single)**: Agent-08 — depends on Agent-05 + Agent-07 contracts.
- **P5 (2-way parallel)**: Agent-09, Agent-10 — final sweep + gating.

## Estimated wall-clock if all phases run optimally

| Phase | Sequential equivalent | Parallel wall-clock | Saved |
|---|---|---|---|
| P1 | 1 unit | 1 unit | 0 |
| P2 | 3 units | 1 unit | 2 |
| P3 | 3 units | 1 unit | 2 |
| P4 | 1 unit | 1 unit | 0 |
| P5 | 2 units | 1 unit | 1 |
| **Total** | **10 units** | **5 units** | **5 units (50%)** |

Units are abstract — actual durations depend on agent throughput. The point is the dependency graph permits ~50% wall-clock reduction by running 2–3 agents at a time during P2/P3.
