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

Six phases, run in order. Each consumes the output of the previous.

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

Select a target Cell.

#### Opportunistic Food (checked first, all health modes)

A Food is **Opportunistic** iff it is Winnable, lies at most two Moves from
our Head (`d_us(f) ≤ 2`), and its Cell is Trap-Safe — the Phase 6 Space-Safety
test applied to the Food Cell itself.

If any Opportunistic Food exists, target the nearest one — smallest `d_us`,
tie-broken by Center Distance — and skip the health-mode logic below.

A Food two Moves away is nearly free: capturing it costs almost no tempo, so
it is taken immediately rather than passed over for a more contested but
distant Food. The Trap-Safe condition keeps this fast path from steering us
into a pocket.

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
unreachable), the candidate Move is undefined; Phases 5–6 still run and
select a Move.

### Phase 5 — Survival Filter

Classify each Move's destination Cell and produce two nested sets:

- **Open Moves** — destination is in bounds AND not occupied by any Snake's
  body (v1: including Tails). An Open Move may still be lost to a head-to-head,
  but — unlike a wall or a body — that depends on the Opponent's choice, so it
  is not *certain* death.
- **Survivable Moves** — the Open Moves that are additionally not a Cell a
  strictly longer Opponent can move into this Turn (a head-to-head we would
  lose). Survivable Moves ⊆ Open Moves.

This phase selects no Move; Phase 6 arbitrates.

### Phase 6 — Space-Safety Arbitration

A Move can be immediately-survivable yet still fatal a few Turns later if it
enters a region too small to hold the Snake — either too small on its own, or
small enough that an Opponent can seal it shut.

Phase 6 arbitrates over the **Survivable Moves** from Phase 5. If there are
none, it arbitrates over the **Open Moves** instead — accepting a possible
head-to-head rather than walking into a certain wall or body. If there are no
Open Moves either, every Move is certain death: emit the first of
`up, down, left, right` whose destination is in bounds, or `up` if none is.

For each Move in the arbitrated set, measured from its destination Cell by
single-source breadth-first search:

- **Reachable Area** — the count of free Cells reachable, with all Snake
  bodies as obstacles (v1: including Tails, consistent with Phase 1). The
  destination Cell counts toward the Area.
- **Guaranteed Area** — the same count, but additionally treating every free
  Cell adjacent to an Opponent's Head as an obstacle. Those are the Cells an
  Opponent could move into next Turn; the Guaranteed Area is the space we
  retain even if an Opponent moves to seal us in.
- **Food In Area** — the number of Food Cells within the Reachable Area.
- **Required Space** = `you.length + Food In Area + 1`. Eating each Food in
  the region grows us by one segment; the `+1` is a buffer against a Food
  spawning in the region while we are inside it.
- The Move is **Space-Safe** iff `Reachable Area ≥ Required Space`.
- The Move is **Trap-Safe** iff `Guaranteed Area ≥ Required Space`. Trap-Safe
  implies Space-Safe, since Guaranteed Area ≤ Reachable Area.

Each Move in the arbitrated set falls into one of three tiers:

1. **Trap-Safe** — survivable even if an Opponent moves to trap us.
2. **Space-Safe only** — survivable unless an Opponent moves to trap us.
3. **Neither** — the region is too small regardless of Opponents.

Select the final Move:

1. If the Phase 4 candidate Move is in the arbitrated set and Trap-Safe,
   emit it.
2. Otherwise, take the highest non-empty tier and emit its Move with the
   largest Guaranteed Area. Tie-break by largest Reachable Area, then by the
   smallest Center Distance of the destination Cell.

Step 2 deliberately abandons the Phase 3 target for one Turn when the target
path would trap us, or could be trapped by an Opponent. Because the algorithm
re-runs from scratch every Turn, the target is re-pursued from the safer
position on the next Turn — including once an Opponent moves off a chokepoint.

Opponent prediction here is deliberately minimal: only one-ply Head moves, and
only as obstacles for the Guaranteed Area. The strategy still does not model
Opponent paths or intent.

## Determinism

Given the same `GameState`, the algorithm must produce the same Move. All
tie-breaking rules are total orders. Implementations must respect them.

## Known limitations (v1)

These are intentional simplifications to be revisited:

- **Lazy Tail handling.** Tails are treated as solid in the Flood Fill
  (Phase 1) and the Space-Safety BFS (Phase 6), even though a Snake's Tail
  vacates next Turn unless it just ate. This under-counts space and can make
  us avoid Cells that would be safe — a conservative error.
- **No head-adjacency danger in Flood Fill.** Opponent Heads are sources of
  their own BFS but Cells adjacent to longer Opponent Heads are not
  pre-emptively avoided in the distance computation. Survival is enforced
  only in Phase 5; Opponent Head moves are accounted for in Phase 6.
- **Hard health threshold.** `health ≤ 20` is a binary cliff. A gradient
  blend between modes would play better.
- **One-ply.** No multi-turn lookahead beyond the Space-Safety check, and
  Opponent prediction is limited to a single Head move (Phase 6 Guaranteed
  Area). Opponent paths and intent are not modelled.

Each of these is a candidate for a future ADR if play quality demands it.
