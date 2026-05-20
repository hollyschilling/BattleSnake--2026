# BattleSnake API (apiversion 1, Standard ruleset)

The wire format this server implements. Deliberately narrowed to the subset
required for Standard play. Fields the engine sends that we do not consume
are listed under "Tolerated but ignored".

Terminology is per [GLOSSARY.md](../GLOSSARY.md). Domain types referenced
here are defined in [domain-model.md](../domain/domain-model.md).

## Endpoints

| Method | Path     | Purpose                                       | Response                  |
|--------|----------|-----------------------------------------------|---------------------------|
| GET    | `/`      | Snake metadata                                | `InfoResponse` (200)      |
| POST   | `/start` | Game start notification                       | 200 OK, empty body        |
| POST   | `/move`  | Per-Turn move request — respond within 500 ms | `MoveResponse` (200)      |
| POST   | `/end`   | Game end notification                         | 200 OK, empty body        |

## InfoResponse (GET /)

```json
{
  "apiversion": "1",
  "author":  "string",
  "color":   "#hex",
  "head":    "default",
  "tail":    "default",
  "version": "semver"
}
```

`apiversion` must be exactly `"1"`. All other fields are configurable per
deployment.

## GameStateRequest (POST /start, /move, /end)

The same payload shape is sent to all three endpoints. The subset we consume:

```json
{
  "game": {
    "id": "uuid",
    "ruleset": { "name": "standard", "version": "string" }
  },
  "turn": 0,
  "board": {
    "height": 11,
    "width":  11,
    "food":   [{ "x": 0, "y": 0 }],
    "snakes": [Snake]
  },
  "you": Snake
}
```

### Snake (wire format)

```json
{
  "id":     "uuid",
  "health": 100,
  "body":   [{ "x": 0, "y": 0 }],
  "head":   { "x": 0, "y": 0 },
  "length": 3
}
```

`body[0]` is the Head, `body[length-1]` is the Tail. `head` duplicates
`body[0]`; if they disagree the request is rejected.

## MoveResponse (POST /move)

```json
{ "move": "up" | "down" | "left" | "right" }
```

A `shout` field is permitted by the engine; this server does not emit one.

## Tolerated but ignored

The parser silently ignores these fields if present:

- `game.map`, `game.source`, `game.timeout`
- `game.ruleset.settings` (all keys)
- `board.hazards` (always treated as empty)
- `snake.name`, `snake.latency`, `snake.shout`, `snake.squad`,
  `snake.customizations`

## Validation

The parser rejects the request with HTTP 400 if:

- `game.ruleset.name` is anything other than `"standard"`. We do not play
  other modes; failing fast is preferable to misbehaving.
- Any Coord falls outside the Board.
- `snake.head` does not equal `snake.body[0]`.
- `you.id` does not appear in `board.snakes`.
- `snake.length` does not equal `count(snake.body)`.

## Coordinate system

Origin `(0, 0)` is the bottom-left Cell. `x` increases right, `y` increases up.
Move deltas are listed in [domain-model.md](../domain/domain-model.md).
