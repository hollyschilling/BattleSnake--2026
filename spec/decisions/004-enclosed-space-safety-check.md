# ADR 004 — Enclosed-Space Safety Check

**Status:** Accepted
**Date:** 2026-05-19

## Context

In play, the Snake repeatedly trapped itself — walking into a region bounded
by its own body, or by its body and a wall, that was too small to hold its
length. A few Turns after entering, it had nowhere to go and collided with
itself.

The v1 move strategy had no awareness of enclosed space:

- Phase 4 (Path Extraction) emits the first step of the shortest path to the
  target. The path can thread into a pocket; nothing checks the pocket's size.
- Phase 5 (Survival Filter) only inspected the **single** destination Cell of
  a Move. A Move into a 4-Cell pocket looks perfectly survivable one Cell
  ahead.

The Flood Fill yields correct *distances*, but distance does not answer
"is this region large enough to survive in." A 5-Cell pocket has valid BFS
distances 1–5 regardless of whether the Snake is length 3 or length 30.

The check was raised during the original design discussion and deferred — it
"didn't seem necessary" for the initial build. Play experience showed it is.

## Decision

Add **Phase 6 — Space-Safety Arbitration** to the move strategy (see
[move-strategy.md](../features/move-strategy.md)).

For each immediately-survivable Move, from its destination Cell, compute:

- **Reachable Area** — free Cells reachable by single-source BFS, with all
  Snake bodies as obstacles.
- **Food In Area** — Food Cells within that Area.
- **Required Space** = `our length + Food In Area + 1`.

A Move is **Space-Safe** iff `Reachable Area ≥ Required Space`.

The final Move is the Phase 4 candidate if it is Space-Safe; otherwise the
largest-Reachable-Area Move (preferring Space-Safe Moves, falling back to all
survivable Moves), tie-broken by Center Distance.

## Rationale

- **Why `length + Food In Area + 1`.** The Snake needs `length` Cells just to
  exist without self-collision. Each Food in the region grows it by one
  segment when eaten, so the region must also absorb `Food In Area` growth.
  The `+1` is a deliberate safety buffer: Standard spawns Food over time, and
  one may appear in the region while the Snake is inside it. Counting all
  Food in the region (rather than only Food on the projected path) is the
  conservative choice — over-reserving space is safe; under-reserving is not.
- **Why fall back to largest Reachable Area, not target progress.** When the
  target path would trap us, survival outranks the target. Picking the
  largest-Area Move moves us toward open space; since the algorithm re-runs
  from scratch every Turn, the target is simply re-pursued next Turn from the
  safer position. A target-progress fallback was considered and rejected as
  unnecessary complexity for v1.
- **Why a separate phase, not folded into Phase 5.** Phase 5 answers a
  per-Cell yes/no question (immediate death). Phase 6 answers a per-region
  question and ranks Moves. Keeping them separate preserves Single
  Responsibility: `SurvivalFilter` classifies, `SpaceEvaluator` measures, the
  selector arbitrates.

## Consequences

**Enables:**

- The Snake stops walking into pockets smaller than its body.
- A graceful-degradation path: when every Move traps us, we still pick the
  least-bad (largest-Area) Move instead of an arbitrary one.

**Constrains:**

- Phase 5 changes contract: it now yields the **set** of survivable Moves
  rather than selecting a Move. The final selection moves to the arbitration
  step. `SurvivalFilter::filter()` is removed.
- Up to four extra single-source BFS runs per Turn (one per survivable Move),
  each O(board) ≈ 121 Cells. Negligible against the latency budget in
  [ADR 003](003-deployment-and-latency-budget.md).
- **Lazy Tail handling** (v1) applies to the Space-Safety BFS too: Tails are
  treated as solid, so Reachable Area is *under-counted*. This is a
  conservative error — we may reject a Move that is in fact safe (notably a
  tail-chase escape), but we will not accept a Move that is in fact a trap.
  Revisiting Tail handling would improve both this phase and Phase 1.

## Supersedes / Superseded by

None.
