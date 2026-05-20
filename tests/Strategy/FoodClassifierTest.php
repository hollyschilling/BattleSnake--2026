<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Coord;
use App\Strategy\ClassifiedFood;
use App\Strategy\FloodFill;
use App\Strategy\FoodClassifier;
use PHPUnit\Framework\TestCase;

final class FoodClassifierTest extends TestCase
{
    /**
     * @param list<ClassifiedFood> $foods
     */
    private function find(array $foods, int $x, int $y): ClassifiedFood
    {
        foreach ($foods as $f) {
            if ($f->coord->equals(new Coord($x, $y))) {
                return $f;
            }
        }
        self::fail("Food at ({$x}, {$y}) not classified.");
    }

    public function testReachableWhenDistanceLessThanHealth(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 5, [[5, 5]])
            ->food([[5, 8]])           // distance 3, health 5 → reachable
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);

        self::assertTrue($this->find($foods, 5, 8)->isReachable);
    }

    public function testNotReachableWhenDistanceEqualsHealth(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 3, [[5, 5]])
            ->food([[5, 8]])           // distance 3 == health 3 → not reachable
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);

        self::assertFalse($this->find($foods, 5, 8)->isReachable);
    }

    public function testWinnableWhenStrictlyCloserThanOpponent(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[10, 5]])
            ->food([[6, 5]])           // us d=1, opp d=4 → winnable, margin 3
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);

        $f = $this->find($foods, 6, 5);
        self::assertTrue($f->isWinnable);
        self::assertSame(1, $f->usDistance);
        self::assertSame(4, $f->opponentDistance);
        self::assertSame(3, $f->winningMargin);
        self::assertSame('opp', $f->winningOpponentId);
    }

    public function testWinnableOnTieIfStrictlyLonger(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 5], [0, 4], [0, 3]])
            ->snake('opp', 100, [[10, 5]])
            ->food([[5, 5]])           // both reach in 5
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);

        $f = $this->find($foods, 5, 5);
        self::assertTrue($f->isWinnable);
        self::assertSame(0, $f->winningMargin);
    }

    public function testNotWinnableOnTieIfEqualLength(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 5]])
            ->snake('opp', 100, [[10, 5]])
            ->food([[5, 5]])
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);

        $f = $this->find($foods, 5, 5);
        self::assertTrue($f->isReachable);
        self::assertFalse($f->isWinnable);
    }

    public function testSoloPlayMarksReachableFoodsAsWinnable(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->food([[1, 1]])
            ->build();

        $result = (new FloodFill())->run($state);
        $foods = (new FoodClassifier())->classify($state, $result);

        $f = $this->find($foods, 1, 1);
        self::assertTrue($f->isWinnable);
        self::assertNull($f->winningOpponentId);
        self::assertSame(PHP_INT_MAX, $f->opponentDistance);
    }
}
