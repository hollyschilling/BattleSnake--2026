# ADR 009 — Treat Equal-Length Head-to-Head as Fatal

**Status:** Accepted
**Date:** 2026-05-20

## Context

Phase 5's Survival Filter rejected a Move only when a **strictly longer**
Opponent could move into the destination Cell. Equal-length Opponents were
ignored, so the filter let the Snake steer into a Cell an equal-length Opponent
could also enter.

The game rules resolve a head-to-head — both heads moving onto the same Cell —
by length: the longer Snake survives, and **equal-length snakes both die**. An
equal-length head-to-head is therefore mutual death, not a safe Move.

The original "strictly longer" boundary treated an equal-length head-to-head as
a strategic *trade*: we die, but so does the Opponent. For this Snake that
never pays off:

- **1v1** — an equal-length head-to-head is a draw (both dead), not a win.
- **3+ snakes** — we are dead and the others play on: strictly a loss.

In play, the Snake repeatedly lost games by volunteering for equal-length
head-to-heads.

## Decision

The Survival Filter now treats any Opponent of length **≥ ours** as a
head-to-head risk. Only strictly shorter Opponents — head-to-heads we win — are
ignored.

In [SurvivalFilter](../../src/Strategy/SurvivalFilter.php), the skip condition
changes from `opponent.length <= our.length` to `opponent.length < our.length`.

## Rationale

- An equal-length head-to-head kills us. For a survival-focused Snake, that is
  a loss; avoiding it is correct.
- We still do not avoid head-to-heads against strictly shorter Opponents — we
  win those — and we do not seek them either (no aggression strategy; see the
  deferred reframe noted in [ADR 006](006-opportunistic-food-capture.md)).
- The forced-draw option is retained: an equal-length contest Cell is still an
  **Open Move**, so when the Snake is cornered and no Survivable Move exists,
  the open-move fallback ([ADR 007](007-least-bad-fallback.md)) still takes the
  head-to-head gamble — the Opponent may swerve.

## Consequences

**Enables:**

- The Snake no longer walks into mutual-death head-to-heads when a genuinely
  safe Move is available.

**Constrains:**

- Slightly more cautious: the Survivable set shrinks near equal-length
  Opponents, shifting more decisions to the open-move fallback when cornered.

## Supersedes / Superseded by

None. Refines the Phase 5 head-to-head rule; the strictly-longer boundary was
never recorded in its own ADR.
