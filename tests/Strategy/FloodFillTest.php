<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Coord;
use App\Strategy\FloodFill;
use PHPUnit\Framework\TestCase;

final class FloodFillTest extends TestCase
{
    public function testHeadDistanceIsZero(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->build();

        $result = (new FloodFill())->run($state);

        self::assertSame(0, $result->distance('us', new Coord(5, 5)));
    }

    public function testNeighborDistancesAreOne(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->build();

        $result = (new FloodFill())->run($state);

        self::assertSame(1, $result->distance('us', new Coord(5, 6)));
        self::assertSame(1, $result->distance('us', new Coord(5, 4)));
        self::assertSame(1, $result->distance('us', new Coord(4, 5)));
        self::assertSame(1, $result->distance('us', new Coord(6, 5)));
    }

    public function testMidBodyBlocksFloodFillButVacatingTailDoesNot(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5], [5, 4], [5, 3]])
            ->build();

        $result = (new FloodFill())->run($state);

        // (5,4) is a mid-body segment — a solid obstacle, never reached.
        self::assertSame(PHP_INT_MAX, $result->distance('us', new Coord(5, 4)));
        // (5,3) is the tail — it vacates, so the BFS routes around to reach it.
        self::assertSame(4, $result->distance('us', new Coord(5, 3)));
    }

    public function testOwnerIsCloserSnake(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('a', 100, [[0, 5]])
            ->snake('b', 100, [[10, 5]])
            ->you('a')
            ->build();

        $result = (new FloodFill())->run($state);

        self::assertSame('a', $result->owner(new Coord(1, 5)));
        self::assertSame('b', $result->owner(new Coord(9, 5)));
    }

    public function testEqualDistanceEqualLengthIsContested(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('a', 100, [[0, 5]])
            ->snake('b', 100, [[10, 5]])
            ->you('a')
            ->build();

        $result = (new FloodFill())->run($state);

        self::assertNull($result->owner(new Coord(5, 5)));
    }

    public function testEqualDistanceLongerSnakeWinsOwnership(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('a', 100, [[0, 5], [0, 4], [0, 3]])
            ->snake('b', 100, [[10, 5]])
            ->you('a')
            ->build();

        $result = (new FloodFill())->run($state);

        self::assertSame('a', $result->owner(new Coord(5, 5)));
    }

    public function testTerritorySizeCountsOwnedCells(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('a', 100, [[0, 5]])
            ->snake('b', 100, [[10, 5]])
            ->you('a')
            ->build();

        $result = (new FloodFill())->run($state);

        // a covers left half (x in 0..4 plus contested column 5 excluded), b mirrors.
        self::assertGreaterThan(0, $result->territorySize('a'));
        self::assertGreaterThan(0, $result->territorySize('b'));
        self::assertSame($result->territorySize('a'), $result->territorySize('b'));
    }

    public function testPathFromHeadToReachableTargetIsContiguous(): void
    {
        $state = (new StateBuilder())
            ->size(7, 7)
            ->snake('us', 100, [[1, 1]])
            ->build();

        $result = (new FloodFill())->run($state);
        $path = $result->pathFrom('us', new Coord(3, 3));

        self::assertCount(5, $path);
        self::assertTrue($path[0]->equals(new Coord(1, 1)));
        self::assertTrue($path[4]->equals(new Coord(3, 3)));

        for ($i = 1; $i < count($path); $i++) {
            self::assertSame(1, $path[$i - 1]->manhattanDistanceTo($path[$i]));
        }
    }

    public function testPathIsEmptyForUnreachableTarget(): void
    {
        // A mid-body segment is a solid obstacle the BFS never reaches.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5], [5, 4], [5, 3]])
            ->build();

        $result = (new FloodFill())->run($state);

        self::assertSame([], $result->pathFrom('us', new Coord(5, 4)));
    }

    public function testPredecessorPrefersCenterCloserNeighborOnTie(): void
    {
        // Head at (1,1) on a 3x3 board (center is (1,1)). Target (2,2):
        //  - via (2,1) using Up: predecessor center-distance = 1
        //  - via (1,2) using Right: predecessor center-distance = 1
        // Tied center distance; spec says Up wins over Right by move-order.
        // The predecessor of (2,2) must therefore be (2,1).
        $state = (new StateBuilder())
            ->size(3, 3)
            ->snake('us', 100, [[1, 1]])
            ->build();

        $result = (new FloodFill())->run($state);
        $path = $result->pathFrom('us', new Coord(2, 2));

        self::assertCount(3, $path);
        self::assertTrue($path[0]->equals(new Coord(1, 1)));
        self::assertTrue($path[1]->equals(new Coord(2, 1)));
        self::assertTrue($path[2]->equals(new Coord(2, 2)));
    }
}
