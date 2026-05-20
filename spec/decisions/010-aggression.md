# ADR 010 — Aggression: Weaker-Opponent Space Denial

**Status:** Accepted
**Date:** 2026-05-20

## Context

The Snake plays purely for survival and food. In play it passed up clear
chances to cut off weaker opponents — slide into their lane against a wall, or
hook a dead end in front of them. Two specific tactics were raised:

- **Wall cap** — slide into an opponent's lane ahead of them so our body and
  the wall sandwich them.
- **Dead-end hook** — bend an L in front of an opponent so they run out of room.

Both are the same idea: place our body where the opponent's free space is, to
shrink or seal it. They are worth adding now that [ADR 009](009-equal-length-head-to-head.md)
makes being longer a real weapon (we win head-to-heads against shorter snakes).

## Decision

Add an **Aggression** step to Phase 3 — Target Selection (see
[move-strategy.md](../features/move-strategy.md)), in Normal-Health Mode,
ahead of food.

Rather than pattern-matching the two specific board geometries (brittle), it
generalises both: each Turn, evaluate our Trap-Safe Moves, and pick the one
that most shrinks a nearby **Weaker Opponent's Reachable Area** measured from
that Opponent's Head.

Concretely, in a new `AggressionEvaluator`:

1. **Engage** only when `health > 20`, and a Weaker Opponent (strictly shorter
   than us) has its Head within 4 Moves (Manhattan) of ours. Target the
   nearest such Opponent.
2. For each Trap-Safe Move, simulate our Snake making it (a hypothetical
   `GameState`) and compute the target Opponent's Reachable Area from its Head.
3. If the smallest resulting Reachable Area is below the Opponent's *current*
   Reachable Area, take the Move that achieves it — there is a block to make.
   Otherwise yield to the food logic.

Priority: Opportunistic Food > Low-Health food > **Aggression** > contested
food > Center. Survival and Trap-Safety (Phases 5–6) still gate every Move.

## Rationale

- One general "shrink the Opponent's space" rule produces both the wall cap and
  the dead-end hook, and more, instead of two fragile special cases.
- It reuses the existing flood fill (`SpaceEvaluator::reachableAreaFrom()`).
- Under the one-ply model the multi-Turn cutoffs **emerge** Turn by Turn — each
  Turn we pick the most-shrinking Move — rather than being a committed plan that
  breaks if the Opponent swerves. That is more robust, not less.
- **Safe by construction:** Aggression only ever selects among Trap-Safe Moves,
  so it can change *which* safe Move we make but never make an unsafe one. The
  worst case is a wasted Turn, never a death.
- Restricting to **strictly shorter** Opponents means a cutoff that ends in a
  head-to-head is one we win.

## Consequences

**Enables:**

- The Snake actively cuts off weaker opponents instead of passing up the chance.

**Constrains / residual approximations:**

- New `AggressionEvaluator` (depends on `SurvivalFilter` and `SpaceEvaluator`);
  `TargetSelector` gains it as a dependency.
- Extra flood fills when Aggression is engaged — a handful of O(board) scans.
  Negligible against the [ADR 003](003-deployment-and-latency-budget.md) budget.
- **One opponent at a time.** Only the nearest Weaker Opponent is targeted.
- **One-ply.** The Opponent also moves; a half-built cutoff can be escaped. We
  re-evaluate every Turn, so this self-corrects.
- The engagement gate uses **Manhattan** distance — a cheap proxy for "nearby";
  an Opponent walled off but close in Manhattan terms simply yields no
  shrinking Move and Aggression falls through.

## Supersedes / Superseded by

None. The deferred reframe of food as a health-only objective
([ADR 006](006-opportunistic-food-capture.md)) is now less likely — with
Aggression, length is a weapon — but that revisit is still future work.
