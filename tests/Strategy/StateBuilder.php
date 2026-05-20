<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Board;
use App\Domain\Coord;
use App\Domain\GameState;
use App\Domain\Snake;

/**
 * Test helper. Builds {@see GameState} from concise body arrays so tests stay readable.
 */
final class StateBuilder
{
    private int $width = 11;
    private int $height = 11;
    /** @var list<Coord> */
    private array $food = [];
    /** @var list<Snake> */
    private array $snakes = [];
    private ?string $youId = null;

    public function size(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;
        return $this;
    }

    /**
     * @param list<array{int, int}> $body Pairs of [x, y], head first.
     */
    public function snake(string $id, int $health, array $body): self
    {
        $coords = array_map(static fn (array $xy): Coord => new Coord($xy[0], $xy[1]), $body);
        $this->snakes[] = new Snake(id: $id, health: $health, body: $coords);
        return $this;
    }

    /**
     * @param list<array{int, int}> $cells
     */
    public function food(array $cells): self
    {
        foreach ($cells as $xy) {
            $this->food[] = new Coord($xy[0], $xy[1]);
        }
        return $this;
    }

    public function you(string $id): self
    {
        $this->youId = $id;
        return $this;
    }

    public function build(): GameState
    {
        if ($this->snakes === []) {
            throw new \LogicException('At least one snake is required.');
        }
        $youId = $this->youId ?? $this->snakes[0]->id;

        $you = null;
        foreach ($this->snakes as $snake) {
            if ($snake->id === $youId) {
                $you = $snake;
                break;
            }
        }
        if ($you === null) {
            throw new \LogicException("`you` id '{$youId}' is not in snakes.");
        }

        $board = new Board(
            width: $this->width,
            height: $this->height,
            food: $this->food,
            snakes: $this->snakes,
        );
        return new GameState(turn: 0, board: $board, you: $you);
    }
}
