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
