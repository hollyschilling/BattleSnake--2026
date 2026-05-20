# ADR 006 — Opportunistic Close-Food Capture

**Status:** Accepted
**Date:** 2026-05-19

## Context

In play, the Snake repeatedly came within one Move of a Food and did not take
it. This is a direct consequence of [ADR 001](001-most-contended-winnable-food.md):
the Most-Contended Winnable Food heuristic ranks Food by Winning Margin, so an
uncontested Food beside us (a huge margin) is the *lowest* priority while a
contested Food across the board is pursued instead.

A broader question was raised: for a passive Snake that is usually already the
longest and is nowhere near starving, is chasing contested Food — or growth at
all — even worthwhile? That reframe (food as a health/opportunity objective
rather than a growth/denial one) was discussed and **deliberately deferred**.
For now the contested-Food strategy is retained — our length can still pressure
Opponents toward traps — and only opportunistic capture is added.

## Decision

Add an **Opportunistic Food** rule at the top of Phase 3 — Target Selection
(see [move-strategy.md](../features/move-strategy.md)), ahead of both
health-mode branches.

A Food is Opportunistic iff it is Winnable, lies within two Moves of our Head
(`d_us(f) ≤ 2`), and its Cell is Trap-Safe. If any exists, the nearest one
(smallest `d_us`, tie-broken by Center Distance) becomes the target and the
health-mode logic is skipped.

## Rationale

- A Food within two Moves is nearly free — capturing it costs almost no tempo.
  Passing it up to chase a distant contested Food is a poor trade.
- The **Trap-Safe** condition reuses the Phase 6 Space-Safety check on the Food
  Cell, so the fast path will not steer us into a pocket. Phase 6 remains the
  ultimate guard on the actual Move regardless.
- Keeping the change small avoids committing to the larger strategy reframe
  before we have evidence for it. The Most-Contended heuristic still governs
  every Food beyond two Moves.

## Consequences

**Enables:**

- The Snake captures free, safe Food in front of it instead of walking past it.

**Constrains:**

- `TargetSelector` now depends on `SpaceEvaluator` (constructor-injected) to
  evaluate the Trap-Safe condition. One assessment per close Food per Turn —
  negligible against the latency budget in
  [ADR 003](003-deployment-and-latency-budget.md).
- The opportunistic rule only adds a fast path; it does not remove a trap-risky
  close Food from later consideration by the health-mode logic. The trap
  *protection* remains Phase 6, not this rule.

## Supersedes / Superseded by

Does not supersede [ADR 001](001-most-contended-winnable-food.md); the
Most-Contended Winnable Food heuristic remains the target rule for Food more
than two Moves away. The deferred reframe of Food as a health-only objective
is a likely future ADR.
