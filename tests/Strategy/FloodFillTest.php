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

    public function testOwnSnakeBodyIsObstacle(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5], [5, 4], [5, 3]])
            ->build();

        $result = (new FloodFill())->run($state);

        self::assertSame(PHP_INT_MAX, $result->distance('us', new Coord(5, 4)));
        self::assertSame(PHP_INT_MAX, $result->distance('us', new Coord(5, 3)));
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
        // Two-cell board, snake fills both cells — anything beyond is OOB.
        $state = (new StateBuilder())
            ->size(11, 11)
            // Boxed in: head at (0,0); body blocks the only two escape squares.
            ->snake('us', 100, [[0, 0], [1, 0], [0, 1]])
            ->build();

        $result = (new FloodFill())->run($state);
        $path = $result->pathFrom('us', new Coord(5, 5));

        self::assertSame([], $path);
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
