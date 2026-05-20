<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Coord;
use App\Strategy\SpaceEvaluator;
use PHPUnit\Framework\TestCase;

final class SpaceEvaluatorTest extends TestCase
{
    public function testOpenBoardYieldsLargeSafeArea(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5], [5, 4], [5, 3]])
            ->build();

        $assessment = (new SpaceEvaluator())->assess($state, new Coord(6, 5));

        self::assertSame(118, $assessment->reachableArea); // 121 cells − 3 body
        self::assertSame(0, $assessment->foodInArea);
        self::assertSame(4, $assessment->requiredSpace);   // length 3 + 0 + 1
        self::assertTrue($assessment->isSafe);
    }

    public function testSealedPocketIsUnsafe(): void
    {
        // Body seals a 2-cell pocket {(0,0), (0,1)} in the corner.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 0], [1, 1], [1, 2], [0, 2], [0, 3], [0, 4], [1, 4], [2, 4]])
            ->build();

        $assessment = (new SpaceEvaluator())->assess($state, new Coord(0, 0));

        self::assertSame(2, $assessment->reachableArea);
        self::assertSame(0, $assessment->foodInArea);
        self::assertSame(9, $assessment->requiredSpace); // length 8 + 0 + 1
        self::assertFalse($assessment->isSafe);
    }

    public function testFoodInsidePocketRaisesRequiredSpace(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 0], [1, 1], [1, 2], [0, 2], [0, 3], [0, 4], [1, 4], [2, 4]])
            ->food([[0, 1]])
            ->build();

        $assessment = (new SpaceEvaluator())->assess($state, new Coord(0, 0));

        self::assertSame(2, $assessment->reachableArea);
        self::assertSame(1, $assessment->foodInArea);
        self::assertSame(10, $assessment->requiredSpace); // length 8 + 1 food + 1
        self::assertFalse($assessment->isSafe);
    }

    public function testAreaEqualToRequiredSpaceIsSafe(): void
    {
        // 1x5 board, snake length 2 at the top: free strip of 3 below.
        // required = 2 + 0 + 1 = 3; reachable area = 3 → safe at the boundary.
        $state = (new StateBuilder())
            ->size(1, 5)
            ->snake('us', 100, [[0, 4], [0, 3]])
            ->build();

        $assessment = (new SpaceEvaluator())->assess($state, new Coord(0, 2));

        self::assertSame(3, $assessment->reachableArea);
        self::assertSame(3, $assessment->requiredSpace);
        self::assertTrue($assessment->isSafe);
    }

    public function testAreaBelowRequiredSpaceIsUnsafe(): void
    {
        // 1x4 board, snake length 2: free strip of only 2 below. required = 3.
        $state = (new StateBuilder())
            ->size(1, 4)
            ->snake('us', 100, [[0, 3], [0, 2]])
            ->build();

        $assessment = (new SpaceEvaluator())->assess($state, new Coord(0, 1));

        self::assertSame(2, $assessment->reachableArea);
        self::assertSame(3, $assessment->requiredSpace);
        self::assertFalse($assessment->isSafe);
    }
}
