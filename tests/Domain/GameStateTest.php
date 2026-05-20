<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Board;
use App\Domain\Coord;
use App\Domain\GameState;
use App\Domain\Snake;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GameStateTest extends TestCase
{
    private function makeBoard(Snake ...$snakes): Board
    {
        return new Board(width: 11, height: 11, food: [], snakes: $snakes);
    }

    public function testConstructsWhenYouIsPresent(): void
    {
        $us = new Snake(id: 'us', health: 100, body: [new Coord(5, 5)]);
        $opp = new Snake(id: 'opp', health: 100, body: [new Coord(0, 0)]);

        $state = new GameState(turn: 0, board: $this->makeBoard($us, $opp), you: $us);

        self::assertSame(0, $state->turn);
        self::assertSame('us', $state->you->id);
    }

    public function testRejectsWhenYouIsNotInBoardSnakes(): void
    {
        $onBoard = new Snake(id: 'opp', health: 100, body: [new Coord(0, 0)]);
        $stranger = new Snake(id: 'us', health: 100, body: [new Coord(5, 5)]);

        $this->expectException(InvalidArgumentException::class);
        new GameState(turn: 0, board: $this->makeBoard($onBoard), you: $stranger);
    }

    public function testRejectsNegativeTurn(): void
    {
        $us = new Snake(id: 'us', health: 100, body: [new Coord(5, 5)]);

        $this->expectException(InvalidArgumentException::class);
        new GameState(turn: -1, board: $this->makeBoard($us), you: $us);
    }
}
