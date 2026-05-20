# Domain Model

Defines the entities and relationships that the rest of the codebase models.
All entities below correspond to types in the `App\Domain` namespace.

Terminology is per [GLOSSARY.md](../GLOSSARY.md).

## Entities

### Coord

An immutable pair `(x, y)` of non-negative integers identifying a Cell on the
Board. Equality is value-based.

### Move

An enum of `Up`, `Down`, `Left`, `Right`. Each value maps deterministically
to a Coord delta:

| Move    | Delta   |
|---------|---------|
| `Up`    | (0, +1) |
| `Down`  | (0, −1) |
| `Left`  | (−1, 0) |
| `Right` | (+1, 0) |

### Snake

- `id` — string, unique within the game
- `health` — int 0–100
- `body` — non-empty ordered list of `Coord`, Head at index 0
- `length` — int, equal to `count(body)`

Derived (not stored separately):

- `head` == `body[0]`
- `tail` == `body[length-1]`

### Board

- `width`, `height` — positive ints, typically 11
- `food` — list of `Coord`
- `snakes` — list of `Snake` (alive only)

### GameState

- `turn` — int ≥ 0
- `board` — Board
- `you` — Snake (the same logical Snake that appears in `board.snakes`)

`GameState` is the input to the move-selection algorithm and the output of
parsing the `/move` request body. It is immutable once constructed.

## Relationships

- `you` is always one of `board.snakes` (matched by `id`).
- All Coords referenced anywhere must satisfy
  `0 ≤ x < board.width` and `0 ≤ y < board.height`. Inputs that violate this
  are malformed and rejected at parse time.
- The last two segments of a Snake's body may share a Coord, indicating the
  Snake just ate and the Tail is growing into the previous segment's
  position. The domain model preserves the raw body list verbatim;
  consumers (e.g., Flood Fill) interpret this as needed.

## What this domain model deliberately omits

The following appear in the wire format but are not modelled because Standard
play does not depend on them. The parser accepts and discards these fields:

- `board.hazards` (always empty in Standard)
- `game.ruleset.settings` (any keys)
- `game.map`, `game.source`, `game.timeout`
- `snake.name`, `snake.latency`, `snake.shout`, `snake.squad`,
  `snake.customizations`
- `snake.head` (we treat `body[0]` as authoritative; the wire `head` is
  validated to match but not stored)
