<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Coord;
use App\Strategy\FloodFill;
use App\Strategy\FoodClassifier;
use App\Strategy\TargetSelector;
use PHPUnit\Framework\TestCase;

final class TargetSelectorTest extends TestCase
{
    public function testNormalHealthPicksMostContendedWinnableFood(): void
    {
        // us at (1,5), opp at (10,5). Foods:
        //  - (2,5): us d=1, opp d=8, margin 7 (uncontested)
        //  - (5,5): us d=4, opp d=5, margin 1 (contested, winnable)
        // Should pick the contested one.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 5]])
            ->snake('opp', 100, [[10, 5]])
            ->food([[2, 5], [5, 5]])
            ->you('us')
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);
        $target = (new TargetSelector())->selectTarget($state, $result, $foods);

        self::assertTrue($target->equals(new Coord(5, 5)));
    }

    public function testNormalHealthFallsBackToCenterWhenNoFood(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 1]])
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);
        $target = (new TargetSelector())->selectTarget($state, $result, $foods);

        self::assertTrue($target->equals(new Coord(5, 5)));
    }

    public function testLowHealthPicksClosestWinnableFood(): void
    {
        // Low health (≤20) overrides "most contended" with "closest".
        // us at (1,5), opp at (10,5):
        //  - (2,5): us d=1, opp d=8, margin 7 (closest)
        //  - (5,5): us d=4, opp d=5, margin 1 (more contested)
        // Low-health should pick (2,5).
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 15, [[1, 5]])
            ->snake('opp', 100, [[10, 5]])
            ->food([[2, 5], [5, 5]])
            ->you('us')
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);
        $target = (new TargetSelector())->selectTarget($state, $result, $foods);

        self::assertTrue($target->equals(new Coord(2, 5)));
    }

    public function testLowHealthFallsBackToReachableMinTerritoryWhenNoWinnable(): void
    {
        // Low-health, no winnable food (opp gets there first), but it's reachable.
        // We expect a Reachable food to be chosen as the target.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 10, [[1, 5]])
            ->snake('opp', 100, [[6, 5], [6, 4], [6, 3]])
            ->food([[5, 5]])  // us d=4 (<10, reachable); opp d=1, opp wins.
            ->you('us')
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);
        $target = (new TargetSelector())->selectTarget($state, $result, $foods);

        self::assertTrue($target->equals(new Coord(5, 5)));
    }
}
