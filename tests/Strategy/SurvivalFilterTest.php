<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Move;
use App\Strategy\SurvivalFilter;
use PHPUnit\Framework\TestCase;

final class SurvivalFilterTest extends TestCase
{
    public function testPassesValidCandidate(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->build();

        self::assertSame(Move::Right, (new SurvivalFilter())->filter($state, Move::Right));
    }

    public function testRejectsOutOfBoundsCandidate(): void
    {
        // Head at (0, 5). Moving Left goes off-board.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 5]])
            ->build();

        $move = (new SurvivalFilter())->filter($state, Move::Left);

        self::assertNotSame(Move::Left, $move);
    }

    public function testRejectsMoveIntoOwnBody(): void
    {
        // Head at (5,5); body extends down to (5,4). Moving Down dies.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5], [5, 4], [5, 3]])
            ->build();

        $move = (new SurvivalFilter())->filter($state, Move::Down);

        self::assertNotSame(Move::Down, $move);
    }

    public function testRejectsMoveIntoOtherSnakeBody(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[6, 6], [6, 5], [6, 4]])
            ->you('us')
            ->build();

        // Right moves to (6,5), which is opp's body.
        $move = (new SurvivalFilter())->filter($state, Move::Right);

        self::assertNotSame(Move::Right, $move);
    }

    public function testRejectsHeadToHeadAgainstStrictlyLongerOpponent(): void
    {
        // us at (5,5) length 1; opp at (5,7) length 2. Both can move into (5,6).
        // opp is strictly longer ⇒ we must not move there.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[5, 7], [5, 8]])
            ->you('us')
            ->build();

        $move = (new SurvivalFilter())->filter($state, Move::Up);

        self::assertNotSame(Move::Up, $move);
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

        $move = (new SurvivalFilter())->filter($state, Move::Up);

        self::assertSame(Move::Up, $move);
    }

    public function testFallsBackToCenterClosestWhenCandidateDies(): void
    {
        // Head at (0,5). Moving Left dies (OOB). Other moves: Up (0,6), Down (0,4), Right (1,5).
        // Center is (5,5). Distances after move: Up=6, Down=6, Right=4. Right wins.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 5]])
            ->build();

        $move = (new SurvivalFilter())->filter($state, Move::Left);

        self::assertSame(Move::Right, $move);
    }

    public function testReturnsUpWhenAllMovesAreFatal(): void
    {
        // Walled into a 1x1 region: head at (0,0), body wraps around.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 0], [1, 0], [0, 1]])
            ->build();

        // Up→(0,1) own body, Right→(1,0) own body, Down→(0,-1) OOB, Left→(-1,0) OOB.
        $move = (new SurvivalFilter())->filter($state, null);

        self::assertSame(Move::Up, $move);
    }
}
