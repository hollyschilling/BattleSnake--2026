<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Move;
use App\Strategy\AggressionEvaluator;
use App\Strategy\SpaceEvaluator;
use App\Strategy\SurvivalFilter;
use PHPUnit\Framework\TestCase;

final class AggressionEvaluatorTest extends TestCase
{
    private function evaluator(): AggressionEvaluator
    {
        return new AggressionEvaluator(new SurvivalFilter(), new SpaceEvaluator());
    }

    public function testPicksTheMoveThatTrapsAWeakerOpponent(): void
    {
        // The opponent (length 3) runs up column 0 against the left wall, head
        // at (0,4). We (length 5) are alongside in column 1, ahead of them.
        // Moving Left to (0,6) caps their lane — they are sealed into 2 cells.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[1, 6], [1, 5], [1, 4], [1, 3], [1, 2]])
            ->snake('opp', 90, [[0, 4], [0, 3], [0, 2]])
            ->you('us')
            ->build();

        self::assertSame(Move::Left, $this->evaluator()->aggressiveMove($state));
    }

    public function testReturnsNullWhenHealthIsCritical(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 15, [[1, 6], [1, 5], [1, 4], [1, 3], [1, 2]])
            ->snake('opp', 90, [[0, 4], [0, 3], [0, 2]])
            ->you('us')
            ->build();

        self::assertNull($this->evaluator()->aggressiveMove($state));
    }

    public function testReturnsNullWhenNoWeakerOpponentInRange(): void
    {
        // The opponent is the same length as us — not a Weaker Opponent.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[1, 6], [1, 5], [1, 4], [1, 3], [1, 2]])
            ->snake('opp', 90, [[0, 4], [0, 3], [0, 2], [0, 1], [0, 0]])
            ->you('us')
            ->build();

        self::assertNull($this->evaluator()->aggressiveMove($state));
    }

    public function testReturnsNullWhenOpponentIsOutOfRange(): void
    {
        // A weaker opponent, but its head is more than 4 Moves away.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[1, 6], [1, 5], [1, 4], [1, 3], [1, 2]])
            ->snake('opp', 90, [[10, 0], [10, 1], [10, 2]])
            ->you('us')
            ->build();

        self::assertNull($this->evaluator()->aggressiveMove($state));
    }

    public function testReturnsNullWhenThereIsNoOpponent(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[1, 6], [1, 5], [1, 4], [1, 3], [1, 2]])
            ->build();

        self::assertNull($this->evaluator()->aggressiveMove($state));
    }
}
