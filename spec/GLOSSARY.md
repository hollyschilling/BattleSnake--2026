# Glossary

Canonical terminology for the BattleSnake project. All code, comments, and
documentation must use these terms exactly. New terms introduced in code must
be added here before the PR merges.

## Game concepts

- **Board** — The grid on which the game is played. Standard size is 11×11.
  Origin (0, 0) is the bottom-left cell.
- **Cell** — A single (x, y) coordinate on the Board.
- **Snake** — A participant in the game, owning a head, body, length, and
  health.
- **Us** — The Snake controlled by this server. Identified in the request
  payload as the `you` field.
- **Opponent** — Any Snake other than Us.
- **Head** — The first segment of a Snake's body. Same as `body[0]`.
- **Body** — The ordered list of Cells occupied by a Snake, from Head to Tail.
- **Tail** — The last segment of a Snake's body (`body[length-1]`).
- **Vacating Tail** — a Snake's Tail on a Turn when that Snake did not just
  eat. The Tail moves off its Cell next Turn, so the Cell is safe to enter. A
  Snake "just ate" when its last two body segments share a Cell
  (`body[length-1] == body[length-2]`).
- **Food** — A Cell that, when entered, restores a Snake's Health to 100 and
  grows the Snake by one segment.
- **Health** — An integer (0–100) representing a Snake's remaining Turns to
  live without eating. Decreases by 1 each Turn.
- **Move** — One of `up`, `down`, `left`, `right`. The output of the `/move`
  endpoint.
- **Turn** — A single game tick during which all live Snakes choose and
  execute a Move simultaneously.
- **Ruleset** — The variant rules of the current game. This project supports
  only `standard`.

## Algorithmic concepts

- **Obstacle Cell** — a Cell occupied by a Snake body segment other than a
  Vacating Tail. Obstacle Cells block movement in the Flood Fill, the Survival
  Filter, and the Space-Safety check.
- **Flood Fill** — A multi-source breadth-first search from every live Snake's
  Head, computing, for each Cell, which Snake reaches it first and after how
  many Moves.
- **Owner** — The Snake assigned to a Cell by the Flood Fill — the Snake with
  the smallest distance to that Cell. Ties are resolved by length (longer
  wins; equal lengths leave the Cell unowned).
- **Territory** — The set of Cells owned by a given Snake.
- **Reachable Food** — A Food Cell `f` for which `d_us(f) < our_health`.
  Strict less-than: equal means we arrive at 0 HP and die on arrival.
- **Winnable Food** — A Reachable Food where Us reaches it before any
  Opponent, or simultaneously with an Opponent whom Us is strictly longer
  than.
- **Winning Margin** — For a Winnable Food `f`, the value
  `min(d_opp(f)) − d_us(f)`. Always ≥ 0. Smaller margins are more contested.
- **Most-Contended Winnable Food** — The Winnable Food with the smallest
  Winning Margin. The default target in Normal-Health Mode.
- **Opportunistic Food** — a Winnable Food at most two Moves from our Head
  whose Cell is Trap-Safe. When one exists it is targeted immediately, ahead
  of the health-mode target logic.
- **Weaker Opponent** — an Opponent strictly shorter than us. A head-to-head
  against a Weaker Opponent is one we win, which makes aggressive blocking of
  it safe.
- **Aggression** — in Normal-Health Mode, targeting the Trap-Safe Move that
  most shrinks a nearby Weaker Opponent's Reachable Area, to cut off its
  space. Checked ahead of food.
- **Normal-Health Mode** — Active when `health > 20`. Targeting prioritizes
  food denial.
- **Low-Health Mode** — Active when `health ≤ 20`. Targeting prioritizes
  survival.
- **Center** — The Cell `(floor(width/2), floor(height/2))`. Used as a
  tie-breaker and as the fallback target when no food applies.
- **Center Distance** — Manhattan distance from a Cell to the Center.
- **Reachable Area** — for a given Cell, the count of free Cells reachable
  from it by a single-source breadth-first search with all Snake bodies as
  obstacles. The Cell itself counts toward its own Reachable Area.
- **Food In Area** — the number of Food Cells within a Reachable Area.
- **Required Space** — `our length + Food In Area + 1`. The minimum Reachable
  Area a Move's destination must have for the Move to be Space-Safe.
- **Space-Safe** — a Move whose destination Cell has a Reachable Area at
  least equal to its Required Space — i.e., a region large enough to hold
  the Snake even after eating all Food in it (plus a one-segment buffer).
- **Guaranteed Area** — the Reachable Area of a Cell recomputed with every
  free Cell adjacent to an Opponent's Head also treated as an obstacle. It is
  the space the Snake retains even if an Opponent moves to seal it in.
- **Trap-Safe** — a Move whose destination Cell has a Guaranteed Area at
  least equal to its Required Space — i.e., Space-Safe even against an
  adversarial one-ply Opponent move.
- **Open Move** — a Move whose destination Cell is in bounds and not an
  Obstacle Cell. It may still be lost to a head-to-head, but that depends on
  the Opponent's choice, so it is not *certain* death.
- **Survivable Move** — an Open Move that is additionally not a Cell a longer
  or equal-length Opponent can move into this Turn. A Move with no immediate
  way to die.
