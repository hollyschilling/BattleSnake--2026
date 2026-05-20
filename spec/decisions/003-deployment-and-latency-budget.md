# ADR 003 — Deployment & Latency Budget

**Status:** Accepted
**Date:** 2026-05-19

## Context

The BattleSnake engine enforces a 500 ms round-trip timeout on `POST /move`.
After accounting for network latency in both directions, the practical
in-server compute budget is approximately **400 ms**.

A concern was raised: is PHP fast enough to run the move algorithm
(multi-source Flood Fill over up to 8 Snakes, plus Food Classification and
target selection) within that budget?

This ADR records the analysis and the operational requirements that follow
from it.

## Decision

PHP is more than sufficient for this workload. The constraint is met by:

1. Running PHP 8.3+ with **OPcache enabled** in production.
2. Building Composer autoload with `--classmap-authoritative --optimize`.
3. **Co-locating the server with the game engine's region.** Network latency,
   not compute, is the dominant cost.
4. Avoiding deployment topologies that pay PHP bootstrap cost per request
   (cold-starting serverless functions, FastCGI without OPcache).

These are operational requirements for any production deployment. Failure
to satisfy them does not invalidate the algorithm — it merely consumes
budget unnecessarily.

## Rationale

### Algorithmic work is trivially small

The Board is 11×11 = 121 Cells. Worst-case (8 Snakes):

| Step                                        | Operations          |
|---------------------------------------------|---------------------|
| Multi-source BFS, 8 Snakes × 121 Cells × 4  | ~3,900 cell visits  |
| Food Classification (~12 foods × ~10 lookups)| ~120 ops           |
| Path reconstruction (Manhattan path ≤ 22)   | ~22 ops             |
| **Total**                                   | **< 5,000 ops**     |

PHP 8.3 executes this comfortably in single-digit milliseconds with a naive
implementation. No JIT, packing, or micro-optimisation is required.

### Per-request latency breakdown (typical, commodity VPS)

| Stage                               | Cost          |
|-------------------------------------|---------------|
| FPM handoff (OPcache on)            | ~2–5 ms       |
| Slim 4 routing & middleware         | ~3–8 ms       |
| JSON decode of request body         | ~1 ms         |
| Build Domain types                  | ~1 ms         |
| Algorithm (Flood Fill + selection)  | ~3–8 ms       |
| JSON encode of response             | < 1 ms        |
| **Total in-PHP**                    | **~10–25 ms** |

This leaves 350+ ms for network round-trip and slack. The algorithm could
run 10× slower than estimated and still meet the budget.

### The dominant risks are operational, not algorithmic

In rough order of impact on the budget:

1. **Geographic distance from the game engine.** The engine is hosted in a
   single region. Cross-continent round-trips can consume 150–300 ms by
   themselves. This is the single largest determinant of whether we meet
   the timeout.
2. **OPcache disabled.** Without OPcache, PHP re-parses every file on every
   request — typically 20–50 ms of avoidable overhead.
3. **Non-optimized Composer autoloader.** Add another 5–15 ms of avoidable
   overhead per request.
4. **Cold starts on serverless platforms** (e.g., Lambda min-instances=0).
   A PHP cold start can be 200–500 ms. If we deploy to such a platform,
   we must keep instances warm or use a runtime that avoids per-request
   bootstrap (FrankenPHP, RoadRunner, Swoole).

## Consequences

**Enables:**

- A naive, readable algorithm implementation is acceptable for v1. Premature
  optimisation is explicitly discouraged.
- Clear operational checklist for production deployment.

**Constrains:**

- Hosting choice is non-trivial. Region matters more than CPU class.
- We must not deploy to cold-start-prone serverless platforms without a
  warmth strategy.
- OPcache and optimized autoload are deployment requirements, not
  recommendations.

### Optimization levers (if ever needed)

In rough order of effort, should latency become a real concern:

1. Pre-compute neighbor lists per Cell.
2. Use `SplFixedArray` or int-indexed arrays in the BFS hot path.
3. Move from PHP-FPM to FrankenPHP or RoadRunner — long-lived worker
   processes that skip the bootstrap on each request.
4. Enable OPcache tracing JIT — 2–3× speedup on BFS-style loops.

None of these are needed for v1. They are listed for completeness and as
follow-up ADR candidates if the v1 measurements show otherwise.

## Verification

The first production deployment will record `/move` response times
(server-side) and publish a measurement against this ADR's predictions.
If measured times fall outside the predicted range by more than 2×, this
ADR is superseded by a new one capturing the divergence and its remedy.

## Supersedes / Superseded by

None.
