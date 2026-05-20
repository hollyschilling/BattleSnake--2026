<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Move;
use App\Strategy\FloodFill;
use App\Strategy\FloodFillMoveSelector;
use App\Strategy\FoodClassifier;
use App\Strategy\SurvivalFilter;
use App\Strategy\TargetSelector;
use PHPUnit\Framework\TestCase;

final class FloodFillMoveSelectorTest extends TestCase
{
    private function selector(): FloodFillMoveSelector
    {
        return new FloodFillMoveSelector(
            floodFill: new FloodFill(),
            foodClassifier: new FoodClassifier(),
            targetSelector: new TargetSelector(),
            survivalFilter: new SurvivalFilter(),
        );
    }

    public function testHeadsTowardCenterWhenNoFood(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 1]])
            ->build();

        // From (1,1) toward center (5,5): first step is Up (1,2) or Right (2,1).
        // Tie-break in flood fill picks Up first (move order).
        $move = $this->selector()->select($state);

        self::assertContains($move, [Move::Up, Move::Right]);
    }

    public function testPursuesNearbyWinnableFood(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 1]])
            ->food([[1, 3]])
            ->build();

        // Food directly above (distance 2). Should go Up.
        $move = $this->selector()->select($state);

        self::assertSame(Move::Up, $move);
    }

    public function testNeverPicksImmediatelyFatalMove(): void
    {
        // Head at (0,0). Only safe moves are Up or Right.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 0]])
            ->build();

        $move = $this->selector()->select($state);

        self::assertContains($move, [Move::Up, Move::Right]);
    }

    public function testLowHealthPrioritizesClosestWinnable(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 15, [[1, 5]])
            ->snake('opp', 100, [[10, 5]])
            ->food([[2, 5], [5, 5]])
            ->you('us')
            ->build();

        // Low health: pick closest winnable (2,5). From (1,5) → Right.
        $move = $this->selector()->select($state);

        self::assertSame(Move::Right, $move);
    }

    public function testAvoidsCollisionWithOpponentBody(): void
    {
        // Head at (5,5). Opponent body blocks (6,5) and (4,5).
        // Up and Down are safe.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[6, 6], [6, 5], [6, 4], [5, 4], [4, 4], [4, 5]])
            ->you('us')
            ->build();

        $move = $this->selector()->select($state);

        self::assertContains($move, [Move::Up]);
    }
}
