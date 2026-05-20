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

    public function testRejectsHeadToHeadAgainstEqualLengthOpponent(): void
    {
        // An equal-length head-to-head kills both snakes (ADR 009), so it is
        // filtered from the survivable set — but it stays an Open Move, a
        // gamble the open-move fallback may still take when nothing is safer.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[5, 7]])
            ->you('us')
            ->build();

        $filter = new SurvivalFilter();

        self::assertNotContains(Move::Up, $filter->survivableMoves($state));
        self::assertContains(Move::Up, $filter->openMoves($state));
    }

    public function testReturnsEmptyWhenAllMovesAreFatal(): void
    {
        // Head at (0,0); (1,0) is mid-body, (0,1) is a doubled tail (the snake
        // just ate, so it stays solid). The other two moves are out of bounds.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 0], [1, 0], [0, 1], [0, 1]])
            ->build();

        self::assertSame([], (new SurvivalFilter())->survivableMoves($state));
    }

    public function testOpenMovesIncludeHeadToHeadRiskRejectedFromSurvivable(): void
    {
        // us length 3 at (0,0): Up (0,1) is mid-body, Down/Left are OOB. Right
        // (1,0) is in bounds and free, but a strictly longer opponent at (2,0)
        // can also move there — a head-to-head risk.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 0], [0, 1], [0, 2]])
            ->snake('opp', 100, [[2, 0], [2, 1], [2, 2], [2, 3]])
            ->you('us')
            ->build();

        $filter = new SurvivalFilter();

        // Right is rejected from survivable (H2H) but kept as an Open Move.
        self::assertSame([], $filter->survivableMoves($state));
        self::assertSame([Move::Right], $filter->openMoves($state));
    }

    public function testOpenMovesExcludeOutOfBoundsAndBody(): void
    {
        // Head at (0,5): Up (0,6) is mid-body, Left is OOB.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 5], [0, 6], [0, 7]])
            ->build();

        $open = (new SurvivalFilter())->openMoves($state);

        self::assertContains(Move::Down, $open);
        self::assertContains(Move::Right, $open);
        self::assertNotContains(Move::Up, $open);
        self::assertNotContains(Move::Left, $open);
    }

    public function testTailChaseMoveIsOpenAndSurvivable(): void
    {
        // A snake coiled in a ring: head (0,0), tail (1,0) adjacent to it.
        // Moving Right onto the vacating tail is the classic tail-chase —
        // now recognised as both Open and Survivable.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[0, 0], [0, 1], [0, 2], [1, 2], [2, 2], [2, 1], [2, 0], [1, 0]])
            ->build();

        $filter = new SurvivalFilter();

        self::assertSame([Move::Right], $filter->openMoves($state));
        self::assertSame([Move::Right], $filter->survivableMoves($state));
    }
}
