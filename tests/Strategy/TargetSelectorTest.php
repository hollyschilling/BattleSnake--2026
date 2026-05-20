<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;
use App\Strategy\AggressionEvaluator;
use App\Strategy\FloodFill;
use App\Strategy\FoodClassifier;
use App\Strategy\SpaceEvaluator;
use App\Strategy\SurvivalFilter;
use App\Strategy\TargetSelector;
use PHPUnit\Framework\TestCase;

final class TargetSelectorTest extends TestCase
{
    private function selectTarget(GameState $state): Coord
    {
        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);
        $targetSelector = new TargetSelector(
            new SpaceEvaluator(),
            new AggressionEvaluator(new SurvivalFilter(), new SpaceEvaluator()),
        );
        return $targetSelector->selectTarget($state, $result, $foods);
    }

    public function testTargetsOpportunisticFoodWithinTwoSpaces(): void
    {
        // Food (5,3) is 2 Moves away, winnable and trap-safe — opportunistic.
        // Food (5,9) is more contended (margin 1) but 4 Moves away. The
        // opportunistic rule must win over the most-contended heuristic.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[9, 8], [9, 7]])
            ->food([[5, 3], [5, 9]])
            ->you('us')
            ->build();

        self::assertTrue($this->selectTarget($state)->equals(new Coord(5, 3)));
    }

    public function testPrefersNearestOpportunisticFood(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->food([[5, 6], [5, 7]]) // distance 1 and 2
            ->build();

        self::assertTrue($this->selectTarget($state)->equals(new Coord(5, 6)));
    }

    public function testNormalHealthPicksMostContendedWinnableFoodBeyondTwoSpaces(): void
    {
        // Both foods are more than 2 Moves away, so the opportunistic rule does
        // not fire. us at (0,5), opp at (10,5):
        //  - (3,5): us d=3, opp d=7, margin 4
        //  - (4,5): us d=4, opp d=6, margin 2  ← most contended
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 5]])
            ->snake('opp', 100, [[10, 5]])
            ->food([[3, 5], [4, 5]])
            ->you('us')
            ->build();

        self::assertTrue($this->selectTarget($state)->equals(new Coord(4, 5)));
    }

    public function testFallsBackToCenterWhenNoFood(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 1]])
            ->build();

        self::assertTrue($this->selectTarget($state)->equals(new Coord(5, 5)));
    }

    public function testLowHealthTakesClosestWinnableFood(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 15, [[1, 5]])
            ->snake('opp', 100, [[10, 5]])
            ->food([[2, 5], [5, 5]])
            ->you('us')
            ->build();

        // (2,5) is one Move away — taken whether by the opportunistic rule or
        // the low-health "closest winnable" rule.
        self::assertTrue($this->selectTarget($state)->equals(new Coord(2, 5)));
    }

    public function testAggressionTargetsAWeakerOpponentOverFood(): void
    {
        // A weaker opponent we can cap is nearby; food sits far away. Aggression
        // outranks contested food, so the target is the capping cell (0,6).
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[1, 6], [1, 5], [1, 4], [1, 3], [1, 2]])
            ->snake('opp', 90, [[0, 4], [0, 3], [0, 2]])
            ->food([[8, 8]])
            ->you('us')
            ->build();

        self::assertTrue($this->selectTarget($state)->equals(new Coord(0, 6)));
    }

    public function testLowHealthFallsBackToReachableMinTerritoryWhenNoWinnable(): void
    {
        // Low health, the food is reachable but the opponent reaches it first
        // (not winnable) and it is more than 2 Moves away (not opportunistic).
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 10, [[1, 5]])
            ->snake('opp', 100, [[6, 5], [6, 4], [6, 3]])
            ->food([[5, 5]])
            ->you('us')
            ->build();

        self::assertTrue($this->selectTarget($state)->equals(new Coord(5, 5)));
    }
}
