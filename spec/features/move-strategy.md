# Feature: Move Strategy

How the server selects a Move on every `/move` request. The algorithm runs
from scratch each Turn — no state is preserved across requests.

Terminology per [GLOSSARY.md](../GLOSSARY.md). Domain types per
[domain-model.md](../domain/domain-model.md). Wire format per
[battlesnake-api.md](../interfaces/battlesnake-api.md).

## Inputs

A validated `GameState`.

## Outputs

A `Move`.

## Algorithm

Four phases, run in order. Each consumes the output of the previous.

### Phase 1 — Flood Fill

Run a multi-source breadth-first search from every live Snake's Head
simultaneously. The output is:

- `dist[snake_id][cell]` — distance in Moves from that Snake's Head to that
  Cell, or ∞ if unreachable.
- `owner[cell]` — the Snake that reaches `cell` in the fewest Moves.
  Tie-breaking by Snake length: longer wins. Equal length → `null`
  (contested; effectively unowned).
- `predecessor[snake_id][cell]` — for path reconstruction. When a Cell can be
  produced by more than one neighbor at the frontier, the predecessor is
  chosen as the neighbor with the smallest Center Distance; further ties are
  broken by a fixed Move ordering of `up, right, down, left`.

Obstacles:

- All Cells occupied by any Snake's `body` are solid for the duration of the
  Flood Fill, including Tails (v1; see "Known limitations" below).
- Board edges are solid.

Heads are sources of the BFS, not obstacles to it.

### Phase 2 — Food Classification

For each Food Cell `f`:

- `d_us(f) = dist[us][f]`.
- `d_opp_min(f) = min over Opponents of dist[opp][f]`. ∞ if no Opponent can
  reach `f`.
- `f` is **Reachable** iff `d_us(f) < you.health`. (Strict: equal means we
  arrive at 0 HP and die.)
- `f` is **Winnable** iff Reachable AND:
  - `d_us(f) < d_opp_min(f)`, OR
  - `d_us(f) == d_opp_min(f)` AND `you.length > opp.length` for the Opponent
    that ties us (the one whose `dist[opp][f]` equals `d_opp_min(f)`).
- `WinningMargin(f) = d_opp_min(f) − d_us(f)`. Defined for Winnable foods
  only; always ≥ 0.

### Phase 3 — Target Selection

Select a target Cell. The mode depends on `you.health`.

#### Normal-Health Mode (`health > 20`)

1. If there is at least one Winnable food, target the **Most-Contended
   Winnable Food** — the Winnable food with the smallest `WinningMargin`.
2. Tie-break: among foods with the smallest margin, pick the one with the
   smallest Center Distance.
3. If no Winnable food exists, target the **Center**.

#### Low-Health Mode (`health ≤ 20`)

1. If there is at least one Winnable food, target the **closest** Winnable
   food (smallest `d_us(f)`). Tie-break by Center Distance.
2. Otherwise, if at least one Reachable food exists, target the Reachable
   food whose winning Opponent has the smallest Territory size
   (`|{c : owner[c] == that_opp}|`). Tie-break by Center Distance.
3. Otherwise, target the Center.

### Phase 4 — Path Extraction

Reconstruct the shortest path from `us.head` to the target Cell using
`predecessor[us]`. The first Cell on that path (the Cell adjacent to our
Head) yields our Move via its delta from `us.head`.

If the target Cell is `us.head` itself (possible when targeting the Center
and we're already there), or the predecessor map yields no path (target
unreachable), apply Phase 5 directly with no candidate Move.

### Phase 5 — Survival Filter

Before emitting the Move, verify the candidate Move's destination Cell:

- is in bounds, AND
- is not occupied by any Snake's body (v1: including Tails), AND
- is not a Cell that a strictly longer Opponent can also move into this
  Turn (head-to-head we would lose).

If the candidate Move fails the filter, fall back to any other Move whose
destination passes. Among multiple passing fallbacks, pick the one whose
destination Cell has the smallest Center Distance.

If no Move passes the filter, we are dead on this Turn regardless. Emit
`up` (the engine requires a valid response).

## Determinism

Given the same `GameState`, the algorithm must produce the same Move. All
tie-breaking rules are total orders. Implementations must respect them.

## Known limitations (v1)

These are intentional simplifications to be revisited:

- **Lazy Tail handling.** Tails are treated as solid in the Flood Fill,
  even though a Snake's Tail vacates next Turn unless it just ate. This can
  cause us to avoid Cells that would be safe.
- **No head-adjacency danger in Flood Fill.** Opponent Heads are sources of
  their own BFS but Cells adjacent to longer Opponent Heads are not
  pre-emptively avoided in the distance computation. Survival is enforced
  only in Phase 5.
- **Hard health threshold.** `health ≤ 20` is a binary cliff. A gradient
  blend between modes would play better.
- **One-ply.** No multi-turn lookahead.

Each of these is a candidate for a future ADR if play quality demands it.
