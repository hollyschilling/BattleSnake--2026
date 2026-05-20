<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Board;
use App\Domain\Coord;
use App\Domain\GameState;
use App\Domain\Snake;
use InvalidArgumentException;

/**
 * Converts a decoded BattleSnake `GameStateRequest` payload into a {@see GameState}.
 *
 * Throws {@see MalformedRequestException} if the payload does not conform to the
 * Standard-only subset specified in /spec/interfaces/battlesnake-api.md.
 */
final class GameStateParser
{
    public function parse(mixed $payload): GameState
    {
        $payload = $this->asArray($payload, 'request body');

        $this->assertStandardRuleset($payload);

        $turn = $this->requireInt($payload, 'turn');
        $board = $this->parseBoard($this->requireArrayField($payload, 'board'));
        $youWire = $this->parseSnake($this->requireArrayField($payload, 'you'), 'you');

        $youOnBoard = null;
        foreach ($board->snakes as $snake) {
            if ($snake->id === $youWire->id) {
                $youOnBoard = $snake;
                break;
            }
        }

        if ($youOnBoard === null) {
            throw new MalformedRequestException(
                "`you` (id={$youWire->id}) is not present in board.snakes."
            );
        }

        try {
            return new GameState(turn: $turn, board: $board, you: $youOnBoard);
        } catch (InvalidArgumentException $e) {
            throw new MalformedRequestException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function assertStandardRuleset(array $payload): void
    {
        $name = $payload['game']['ruleset']['name'] ?? null;
        if ($name !== 'standard') {
            $repr = is_string($name) ? "'{$name}'" : var_export($name, true);
            throw new MalformedRequestException(
                "Only the 'standard' ruleset is supported; received {$repr}."
            );
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function parseBoard(array $payload): Board
    {
        $width = $this->requireInt($payload, 'width');
        $height = $this->requireInt($payload, 'height');

        $food = [];
        foreach ($this->requireArrayField($payload, 'food') as $i => $coordRaw) {
            $food[] = $this->parseCoord($this->asArray($coordRaw, "food[{$i}]"));
        }

        $snakesRaw = $this->requireArrayField($payload, 'snakes');
        if ($snakesRaw === []) {
            throw new MalformedRequestException('board.snakes must contain at least one snake.');
        }

        $snakes = [];
        foreach ($snakesRaw as $i => $snakeRaw) {
            $snakes[] = $this->parseSnake(
                $this->asArray($snakeRaw, "board.snakes[{$i}]"),
                "board.snakes[{$i}]",
            );
        }

        try {
            return new Board(width: $width, height: $height, food: $food, snakes: $snakes);
        } catch (InvalidArgumentException $e) {
            throw new MalformedRequestException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function parseSnake(array $payload, string $context): Snake
    {
        $id = $payload['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new MalformedRequestException("Snake id at {$context} must be a non-empty string.");
        }

        $health = $this->requireInt($payload, 'health');
        $bodyRaw = $this->requireArrayField($payload, 'body');
        if ($bodyRaw === []) {
            throw new MalformedRequestException("Snake '{$id}' body must be non-empty.");
        }

        $body = [];
        foreach ($bodyRaw as $i => $coordRaw) {
            $body[] = $this->parseCoord($this->asArray($coordRaw, "snake '{$id}' body[{$i}]"));
        }

        $head = $this->parseCoord($this->requireArrayField($payload, 'head'));
        if (!$head->equals($body[0])) {
            throw new MalformedRequestException(
                "Snake '{$id}' head ({$head->x}, {$head->y}) does not match body[0] ({$body[0]->x}, {$body[0]->y})."
            );
        }

        $declaredLength = $this->requireInt($payload, 'length');
        if ($declaredLength !== count($body)) {
            throw new MalformedRequestException(
                "Snake '{$id}' length {$declaredLength} does not match body length " . count($body) . '.'
            );
        }

        try {
            return new Snake(id: $id, health: $health, body: $body);
        } catch (InvalidArgumentException $e) {
            throw new MalformedRequestException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function parseCoord(array $payload): Coord
    {
        $x = $this->requireInt($payload, 'x');
        $y = $this->requireInt($payload, 'y');
        return new Coord($x, $y);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function asArray(mixed $value, string $context): array
    {
        if (!is_array($value)) {
            throw new MalformedRequestException("Expected '{$context}' to be an object.");
        }
        return $value;
    }

    /**
     * @param array<array-key, mixed> $payload
     * @return array<array-key, mixed>
     */
    private function requireArrayField(array $payload, string $key): array
    {
        if (!array_key_exists($key, $payload)) {
            throw new MalformedRequestException("Missing required field '{$key}'.");
        }
        if (!is_array($payload[$key])) {
            throw new MalformedRequestException("Field '{$key}' must be an object.");
        }
        return $payload[$key];
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private function requireInt(array $payload, string $key): int
    {
        if (!array_key_exists($key, $payload)) {
            throw new MalformedRequestException("Missing required field '{$key}'.");
        }
        $value = $payload[$key];
        if (!is_int($value)) {
            throw new MalformedRequestException("Field '{$key}' must be an integer.");
        }
        return $value;
    }
}
