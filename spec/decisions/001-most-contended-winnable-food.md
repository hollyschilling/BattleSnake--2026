# ADR 001 — Target the Most-Contended Winnable Food

**Status:** Accepted
**Date:** 2026-05-19

## Context

The move-strategy algorithm produces, on each Turn, a set of Winnable foods
(see [move-strategy.md](../features/move-strategy.md)). When more than one
Food is Winnable, we need a rule for which to target.

Three candidates were considered:

1. **Closest Winnable** — target the Winnable food with smallest `d_us(f)`.
2. **Farthest Winnable** — target the Winnable food with largest `d_us(f)`.
   Rationale offered: leave close foods for incidental pickup; range farther
   to constrict Opponents to a smaller portion of the Board.
3. **Most-Contended Winnable** — target the Winnable food with the smallest
   positive `WinningMargin` (`d_opp_min(f) − d_us(f)`).

## Decision

In Normal-Health Mode, target the **Most-Contended Winnable Food**.

## Rationale

- Foods we'd win comfortably will accrue to us regardless of pursuit. Racing
  for them spends Moves we don't need to spend.
- Contested foods are the marginal ones: if we don't claim them, an
  Opponent will, gaining Health and growth. Targeting them maximizes the
  Health and growth delta we extract per Move.
- "Farthest Winnable" was rejected on reflection. Food spawns are roughly
  uniform over the Board, so ranging to far edges moves us *away* from
  where the next Food is likely to appear. Opponents also drift toward
  Food density, not away from Us, so "constricting" via Food chasing does
  not hold up.
- "Closest Winnable" is the right behavior under Low-Health Mode, where
  survival outweighs denial. Low-Health Mode adopts it.

## Consequences

**Enables:**

- Food-denial play that scales with Opponent count.
- A single comparable scalar (`WinningMargin`) for target selection.

**Constrains:**

- The algorithm will sometimes pass over a close, "free" food in favor of
  a contested one further away. This is intentional but may look strange
  in replays.
- Behavior is sensitive to Flood Fill accuracy: an Opponent's distance
  error changes both whether a Food is Winnable and its margin. Acceptable
  in v1 (lazy Tail handling); revisit if play quality suffers.

## Supersedes / Superseded by

None.
