# ADR 008 — Tail-Aware Obstacles

**Status:** Accepted
**Date:** 2026-05-20

## Context

Every component that builds an obstacle set — the Flood Fill (Phase 1), the
Survival Filter (Phase 5), and the Space-Safety check (Phase 6) — treated
**every** Snake body segment as solid, Tails included. This "lazy Tail
handling" was a v1 simplification flagged from the start of the project and
called out as a Known Limitation and in the Consequences of
[ADR 004](004-enclosed-space-safety-check.md) and
[ADR 005](005-pessimistic-space-check.md).

It cost a game. A coiled length-20 Snake had a safe **tail-chase** available —
moving onto its own Tail's Cell, which the Tail vacates the same Turn — but
the Survival Filter rejected it as a body collision. With every Move rejected,
the (now removed) hardcoded fallback walked the Snake off the board.

A Snake's Tail vacates its Cell next Turn **unless that Snake just ate**. When
a Snake eats, the engine does not recede the Tail that Turn; the Snake's last
two body segments occupy the same Cell — `body[len-1] == body[len-2]` — until
the body catches up. That doubled-segment condition is a reliable, body-only
test for "just ate".

## Decision

Introduce the concept of a **Vacating Tail** — the Tail of a Snake (length ≥ 2)
that did not just eat — and exclude Vacating Tails from every obstacle set.

- `Snake::justAte()` (domain) — `count(body) >= 2 && body[len-1] == body[len-2]`.
- `ObstacleMap` (strategy) — built from a `Board`, holds the set of
  **Obstacle Cells**: every body segment except a Vacating Tail.
- `FloodFill`, `SpaceEvaluator`, and `SurvivalFilter` all build their obstacle
  set from `ObstacleMap`, replacing their own ad-hoc body loops.

This resolves the lazy-Tail Known Limitation; it does not supersede ADR 004 or
ADR 005, whose decisions stand.

## Rationale

- A Vacating Tail Cell is safe to enter: the Tail leaves it on the same Turn
  the Snake (us or an Opponent) moves. Treating it as solid wrongly forbids
  tail-chasing — the standard escape for a long, coiled Snake.
- `body[len-1] == body[len-2]` is the canonical, body-only "just ate" signal;
  no health or food inspection is needed.
- A single shared `ObstacleMap` keeps the Tail rule in one place (DRY) and
  guarantees the three phases agree on what blocks movement.

## Consequences

**Enables:**

- Tail-chasing — the Snake can follow its own (or an Opponent's) receding Tail
  out of a coil.
- More accurate Flood Fill distances and Space-Safety areas: a Vacating Tail
  is correctly counted as reachable free space.

**Constrains / residual approximations:**

- **Opponent Tails** are assumed to vacate unless that Opponent just ate. If an
  Opponent eats next Turn its Tail does not recede; moving onto it would then
  collide. This is an occasional mis-prediction, recomputed every Turn, and is
  the conventional trade-off.
- **Eat-Turn off-by-one in Space-Safety.** `ObstacleMap` decides Tail vacancy
  from the *current* state. When our own Move eats Food, our Tail does not
  recede that Turn, so a Space-Safety area measured through our Vacating Tail
  over-counts by one Cell on an eat Turn. `Required Space` already rises by one
  for the eaten Food, so the error is small and self-correcting; it is left for
  a later refinement.

## Supersedes / Superseded by

None. Resolves the lazy-Tail limitation noted in ADR 004 and ADR 005.
