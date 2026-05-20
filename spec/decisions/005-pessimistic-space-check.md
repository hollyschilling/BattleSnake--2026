# ADR 005 — Pessimistic Space Check (Opponent One-Ply Trap Avoidance)

**Status:** Accepted
**Date:** 2026-05-19

## Context

[ADR 004](004-enclosed-space-safety-check.md) added the Phase 6 space check,
which measures the **Reachable Area** behind a candidate Move and rejects
Moves into regions too small to hold the Snake.

That check builds its obstacle set from **current Snake bodies only**. In
play, Opponents exploited the blind spot: an Opponent Head positioned next to
the single opening of an otherwise-enclosed region can step onto that opening
the following Turn, sealing us inside. The Reachable Area counts the region as
open — the opening is free *now* — so we walk in and are trapped.

The original design deliberately omitted predicting Opponent movement. This
gap is the cost of that decision.

## Decision

Extend Phase 6 with a second, pessimistic flood fill and a tiered selection
(see [move-strategy.md](../features/move-strategy.md)).

- **Guaranteed Area** — the Reachable Area recomputed with every free Cell
  adjacent to an Opponent's Head also treated as an obstacle. Those are the
  Cells an Opponent could move into next Turn.
- A Move is **Trap-Safe** iff `Guaranteed Area ≥ Required Space`.
- Each survivable Move is tiered: Trap-Safe > Space-Safe-only > neither.
  The final Move is the Phase 4 candidate if Trap-Safe; otherwise the
  largest-Guaranteed-Area Move from the highest non-empty tier.

The `Required Space` formula from ADR 004 is unchanged.

## Rationale

- **Why one-ply Opponent prediction is enough.** A trap closes in exactly
  one Opponent move — the Opponent steps onto the chokepoint. Modelling
  Opponent paths further out is unnecessary for this failure mode, and would
  reintroduce the general Opponent-modelling the project deliberately skipped.
  Blocking the Cells adjacent to Opponent Heads is the minimal, targeted slice
  that addresses it.
- **Why a tier, not a hard reject.** Distinguishing "an Opponent *could* trap
  us" (Space-Safe only) from "we are trapped regardless" (neither) lets the
  fallback prefer a might-be-trapped Move over a certainly-doomed one. A pure
  pessimistic gate would conflate the two and could pick the worse Move.
- **Over-pessimism is bounded.** Blocking all four Head-neighbours of every
  Opponent assumes Opponents can be everywhere at once. In open space this is
  harmless — the Area stays far above `Required Space`. It changes the outcome
  only near genuine chokepoints, which is exactly where it should. In crowded
  multi-snake end-games it makes us more cautious, which is the correct
  instinct there.

## Consequences

**Enables:**

- The Snake no longer enters regions an Opponent can seal behind it.
- Graceful degradation: when no Move is Trap-Safe, the tiering still picks the
  best available (Space-Safe over doomed; roomiest within a tier).

**Constrains:**

- One extra single-source BFS per survivable Move (the pessimistic pass) —
  at most four more O(board) ≈ 121-Cell scans per Turn. Negligible against
  the latency budget in [ADR 003](003-deployment-and-latency-budget.md).
- Phase 6's selection rule changes: the candidate Move is auto-emitted only
  when **Trap-Safe** (previously: Space-Safe), and the fallback ranks by
  **Guaranteed Area** within tiers.
- This is still not general Opponent modelling — only one-ply Head moves, and
  only as obstacles for the Guaranteed Area. Opponent intent and paths remain
  unmodelled.

## Supersedes / Superseded by

Refines [ADR 004](004-enclosed-space-safety-check.md); does not supersede it.
The space check and its `Required Space` threshold stand; ADR 005 adds the
pessimistic pass and the tiered selection on top.
