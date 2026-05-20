# ADR 007 — Least-Bad Fallback When No Move Is Survivable

**Status:** Accepted
**Date:** 2026-05-20

## Context

In a league game the Snake (length 20) walked straight off the top edge of the
board while an in-bounds Move was still available — a self-inflicted loss.

The cause: `FloodFillMoveSelector::select()` emitted a hardcoded `Move::Up`
whenever `survivableMoves()` returned an empty set, and the move-strategy spec
backed this up — Phase 5 stated "we are dead this Turn regardless — emit `up`".

That assumption is false. The Survival Filter rejects a Move for one of three
reasons:

1. **Out of bounds** — certain death.
2. **Body collision** — certain death.
3. **Possible losing head-to-head** — death *only if* the Opponent also moves
   into that Cell. The Opponent has its own move to make and may go elsewhere.

An empty Survivable set therefore does **not** mean every Move is fatal — some
rejected Moves may only carry head-to-head *risk*. Emitting a fixed `Move::Up`
ignores this and can pick a guaranteed wall over a survivable gamble.

## Decision

Phase 5 now produces two nested sets:

- **Open Moves** — destination in bounds and not a body Cell.
- **Survivable Moves** — Open Moves minus the losing-head-to-head Cells
  (⊆ Open Moves).

Phase 6 arbitrates the Survivable Moves. If there are none, it arbitrates the
**Open Moves** instead — accepting a possible head-to-head over a certain wall
or body. Only if there are no Open Moves either is death certain, and then it
emits a deterministic in-bounds Move (the first of `up, down, left, right`
whose destination is on the board, or `up` if none is).

## Rationale

- A possible head-to-head is a survivable risk; a wall is not. "Always stay
  alive" means never emitting a certainly-fatal Move when a possibly-survivable
  one exists.
- Reusing the Phase 6 space arbitration on the Open Moves means that even in
  the desperate case we still avoid trapping ourselves among the risky Moves.
- The fix is small and self-contained: `SurvivalFilter` gains `openMoves()`;
  `select()` becomes survivable → open → last-resort.

## Consequences

**Enables:**

- The Snake never walks into a certain wall or body when a head-to-head gamble
  is available.

**Constrains:**

- `SurvivalFilter` exposes a second query, `openMoves()`.
- The hardcoded `Move::Up` is gone; the genuine "all Moves fatal" case now
  emits a deterministic in-bounds Move instead.

This refines the Phase 5/6 behaviour from [ADR 004](004-enclosed-space-safety-check.md)
and [ADR 005](005-pessimistic-space-check.md); it does not supersede them. It
is also a precursor to tail-handling: correct tail handling will further shrink
the set of Moves wrongly classified as fatal, but this fallback is the right
safety net regardless.

## Supersedes / Superseded by

None.
