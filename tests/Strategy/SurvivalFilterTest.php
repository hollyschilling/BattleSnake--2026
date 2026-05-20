<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Move;
use App\Strategy\SurvivalFilter;
use PHPUnit\Framework\TestCase;

final class SurvivalFilterTest extends TestCase
{
    public function testOpenBoardYieldsAllFourMoves(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->build();

        self::assertCount(4, (new SurvivalFilter())->survivableMoves($state));
    }

    public function testExcludesOutOfBoundsMove(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 5]])
            ->build();

        self::assertNotContains(Move::Left, (new SurvivalFilter())->survivableMoves($state));
    }

    public function testExcludesMoveIntoOwnBody(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5], [5, 4], [5, 3]])
            ->build();

        self::assertNotContains(Move::Down, (new SurvivalFilter())->survivableMoves($state));
    }

    public function testExcludesMoveIntoOtherSnakeBody(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[6, 6], [6, 5], [6, 4]])
            ->you('us')
            ->build();

        self::assertNotContains(Move::Right, (new SurvivalFilter())->survivableMoves($state));
    }

    public function testExcludesHeadToHeadAgainstStrictlyLongerOpponent(): void
    {
        // us at (5,5) length 1; opp at (5,7) length 2 — both can enter (5,6).
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[5, 7], [5, 8]])
            ->you('us')
            ->build();

        self::assertNotContains(Move::Up, (new SurvivalFilter())->survivableMoves($state));
    }

    public function testAllowsHeadToHeadAgainstEqualLengthOpponent(): void
    {
        // Per spec, only *strictly longer* opponent collisions are filtered.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[5, 7]])
            ->you('us')
            ->build();

        self::assertContains(Move::Up, (new SurvivalFilter())->survivableMoves($state));
    }

    public function testReturnsEmptyWhenAllMovesAreFatal(): void
    {
        // Head at (0,0), body blocks (1,0) and (0,1); other two moves are OOB.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 0], [1, 0], [0, 1]])
            ->build();

        self::assertSame([], (new SurvivalFilter())->survivableMoves($state));
    }
}
