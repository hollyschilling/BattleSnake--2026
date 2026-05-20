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

        self::assertSame(119, $assessment->reachableArea);  // 121 − 2 obstacles (tail passable)
        self::assertSame(119, $assessment->guaranteedArea); // no opponents → equal
        self::assertSame(0, $assessment->foodInArea);
        self::assertSame(4, $assessment->requiredSpace);    // length 3 + 0 + 1
        self::assertTrue($assessment->isSpaceSafe);
        self::assertTrue($assessment->isTrapSafe);
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
        self::assertFalse($assessment->isSpaceSafe);
        self::assertFalse($assessment->isTrapSafe);
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
        self::assertFalse($assessment->isSpaceSafe);
    }

    public function testAreaEqualToRequiredSpaceIsSafe(): void
    {
        // 1x4 board, snake length 2 at the top. The tail (0,2) vacates, so the
        // reachable strip is (0,1), (0,0) and (0,2) = 3. required = 2 + 0 + 1 = 3
        // → safe exactly at the boundary.
        $state = (new StateBuilder())
            ->size(1, 4)
            ->snake('us', 100, [[0, 3], [0, 2]])
            ->build();

        $assessment = (new SpaceEvaluator())->assess($state, new Coord(0, 1));

        self::assertSame(3, $assessment->reachableArea);
        self::assertSame(3, $assessment->requiredSpace);
        self::assertTrue($assessment->isSpaceSafe);
        self::assertTrue($assessment->isTrapSafe);
    }

    public function testAreaBelowRequiredSpaceIsUnsafe(): void
    {
        // 1x3 board, snake length 2. Even with the tail (0,1) vacating, the
        // reachable strip is only (0,0), (0,1) = 2 < required 3.
        $state = (new StateBuilder())
            ->size(1, 3)
            ->snake('us', 100, [[0, 2], [0, 1]])
            ->build();

        $assessment = (new SpaceEvaluator())->assess($state, new Coord(0, 0));

        self::assertSame(2, $assessment->reachableArea);
        self::assertSame(3, $assessment->requiredSpace);
        self::assertFalse($assessment->isSpaceSafe);
    }

    public function testOpponentHeadAtChokepointMakesAreaNotTrapSafe(): void
    {
        // Pocket {(0,0),(0,1),(0,2)} with a single exit at (0,3). The opponent
        // body walls the pocket; it just ate (doubled tail at (1,2)) so the
        // wall stays solid. The opponent head (2,3) sits one step from (1,3) —
        // the cell linking (0,3) to the open board. Moving to (0,3) looks roomy
        // (Space-Safe) but the opponent can seal (1,3) and trap us.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 4], [0, 5], [0, 6], [0, 7]])
            ->snake('opp', 100, [[2, 3], [2, 2], [2, 1], [2, 0], [1, 0], [1, 1], [1, 2], [1, 2]])
            ->you('us')
            ->build();

        $assessment = (new SpaceEvaluator())->assess($state, new Coord(0, 3));

        self::assertSame(5, $assessment->requiredSpace); // length 4 + 0 + 1
        self::assertTrue($assessment->isSpaceSafe);      // optimistic: opens to the board
        self::assertFalse($assessment->isTrapSafe);      // pessimistic: (1,3) sealed
        self::assertSame(4, $assessment->guaranteedArea); // {(0,3),(0,2),(0,1),(0,0)}
    }
}
