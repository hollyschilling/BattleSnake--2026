<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Coord;
use App\Strategy\ObstacleMap;
use PHPUnit\Framework\TestCase;

final class ObstacleMapTest extends TestCase
{
    public function testExcludesAVacatingTail(): void
    {
        // A normal snake: its tail vacates next turn, so the tail Cell is not
        // an obstacle; head and mid-body are.
        $board = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[5, 5], [5, 4], [5, 3]])
            ->build()
            ->board;

        $map = new ObstacleMap($board);

        self::assertTrue($map->contains(new Coord(5, 5)));   // head
        self::assertTrue($map->contains(new Coord(5, 4)));   // mid-body
        self::assertFalse($map->contains(new Coord(5, 3)));  // vacating tail
    }

    public function testIncludesTheTailOfASnakeThatJustAte(): void
    {
        // Doubled last segment ⇒ just ate ⇒ tail does not recede ⇒ stays solid.
        $board = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5], [5, 4], [5, 3], [5, 3]])
            ->build()
            ->board;

        $map = new ObstacleMap($board);

        self::assertTrue($map->contains(new Coord(5, 3)));
    }

    public function testSingleSegmentSnakeCellIsAnObstacle(): void
    {
        $board = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->build()
            ->board;

        self::assertTrue((new ObstacleMap($board))->contains(new Coord(5, 5)));
    }

    public function testHandlesMultipleSnakes(): void
    {
        $board = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[1, 1], [1, 2], [1, 3]])
            ->snake('opp', 90, [[9, 9], [9, 8], [9, 7]])
            ->build()
            ->board;

        $map = new ObstacleMap($board);

        self::assertTrue($map->contains(new Coord(1, 2)));   // our mid-body
        self::assertFalse($map->contains(new Coord(1, 3)));  // our vacating tail
        self::assertTrue($map->contains(new Coord(9, 8)));   // opponent mid-body
        self::assertFalse($map->contains(new Coord(9, 7)));  // opponent vacating tail
    }

    public function testEmptyCellIsNotAnObstacle(): void
    {
        $board = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->build()
            ->board;

        self::assertFalse((new ObstacleMap($board))->contains(new Coord(0, 0)));
    }
}
