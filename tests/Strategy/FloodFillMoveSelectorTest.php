<?php

declare(strict_types=1);

namespace App\Tests\Strategy;

use App\Domain\Move;
use App\Strategy\AggressionEvaluator;
use App\Strategy\FloodFill;
use App\Strategy\FloodFillMoveSelector;
use App\Strategy\FoodClassifier;
use App\Strategy\SpaceEvaluator;
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
            targetSelector: new TargetSelector(
                new SpaceEvaluator(),
                new AggressionEvaluator(new SurvivalFilter(), new SpaceEvaluator()),
            ),
            survivalFilter: new SurvivalFilter(),
            spaceEvaluator: new SpaceEvaluator(),
        );
    }

    public function testHeadsTowardCenterWhenNoFood(): void
    {
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 1]])
            ->build();

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
        // Head at (5,5). Opponent mid-body blocks (6,5), (5,4) and (4,5); only
        // Up is free. The opponent's tail (4,6) is well clear of our head.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[6, 7], [6, 6], [6, 5], [6, 4], [5, 4], [4, 4], [4, 5], [4, 6]])
            ->you('us')
            ->build();

        $move = $this->selector()->select($state);

        self::assertSame(Move::Up, $move);
    }

    public function testAvoidsTrappingItselfInAPocket(): void
    {
        // The body seals a 2-cell pocket {(0,0),(0,1)}. Food at (0,1) makes the
        // target-path candidate Left — straight into the trap. The space check
        // must override it: the pocket holds 2 cells and we are length 8.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[1, 0], [1, 1], [1, 2], [0, 2], [0, 3], [0, 4], [1, 4], [2, 4]])
            ->food([[0, 1]])
            ->build();

        $move = $this->selector()->select($state);

        self::assertSame(Move::Right, $move);
    }

    public function testGrabsOpportunisticFoodOverDistantContestedFood(): void
    {
        // Food (5,3) is 2 Moves below us — opportunistic. Food (5,9) is more
        // contended but 4 Moves away. We must step toward the close food (Down),
        // not the contested one (Up).
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[5, 5]])
            ->snake('opp', 100, [[9, 8], [9, 7]])
            ->food([[5, 3], [5, 9]])
            ->you('us')
            ->build();

        self::assertSame(Move::Down, $this->selector()->select($state));
    }

    public function testAvoidsSpaceAnOpponentCanSealOff(): void
    {
        // Food sits in a 3-cell pocket {(0,0),(0,1),(0,2)} whose only exit is
        // (0,3). The opponent just ate (doubled tail at (1,2)) so its body wall
        // stays solid. Its head (2,3) is one step from (1,3), the cell linking
        // the pocket to the open board. The greedy target path is Down — into
        // the pocket — but the opponent can seal (1,3) behind us. The
        // pessimistic check must steer us Right.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 4], [0, 5], [0, 6], [0, 7]])
            ->snake('opp', 100, [[2, 3], [2, 2], [2, 1], [2, 0], [1, 0], [1, 1], [1, 2], [1, 2]])
            ->food([[0, 0]])
            ->you('us')
            ->build();

        self::assertSame(Move::Right, $this->selector()->select($state));
    }

    public function testFallsBackToOpenMoveWhenNoSurvivableMove(): void
    {
        // us just ate (doubled tail at (0,1)), so Up is solid — no tail-chase.
        // Down/Left are OOB. Right (1,0) is rejected from the survivable set
        // only because a strictly longer opponent could contest it. With no
        // survivable Move we take the open Right — a possible head-to-head
        // beats the certain self-collision a hardcoded `up` would have caused.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 0], [0, 1], [0, 1]])
            ->snake('opp', 100, [[2, 0], [2, 1], [2, 2], [2, 3]])
            ->you('us')
            ->build();

        self::assertSame(Move::Right, $this->selector()->select($state));
    }

    public function testEmitsInBoundsMoveWhenEveryMoveIsCertainDeath(): void
    {
        // Boxed in and just ate (doubled tail), so there is no tail-chase
        // escape: every Move is out of bounds or into a body. The last-resort
        // emits the first in-bounds Move rather than blindly leaving the board.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 100, [[0, 0], [1, 0], [0, 1], [0, 1]])
            ->build();

        self::assertSame(Move::Up, $this->selector()->select($state));
    }

    public function testCutsOffAWeakerOpponent(): void
    {
        // Weaker opponent (length 3) runs up column 0 against the wall; we
        // (length 5) are alongside and ahead. We move Left to cap their lane.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[1, 6], [1, 5], [1, 4], [1, 3], [1, 2]])
            ->snake('opp', 90, [[0, 4], [0, 3], [0, 2]])
            ->you('us')
            ->build();

        self::assertSame(Move::Left, $this->selector()->select($state));
    }

    public function testEscapesACoilViaTailChase(): void
    {
        // A snake coiled in a ring. Every move but one is a wall or mid-body;
        // the sole survivable move is onto its own vacating tail. Before
        // tail-aware obstacles this was a guaranteed loss.
        $state = (new StateBuilder())
            ->size(11, 11)
            ->snake('us', 90, [[0, 0], [0, 1], [0, 2], [1, 2], [2, 2], [2, 1], [2, 0], [1, 0]])
            ->build();

        self::assertSame(Move::Right, $this->selector()->select($state));
    }

    public function testPicksLargestAreaWhenEveryMoveIsUnsafe(): void
    {
        // 5x5 board. The length-10 body walls it into a 10-cell region (above,
        // reached by Up) and a 5-cell region (below, reached by Down).
        // required = 10 + 0 + 1 = 11, so both regions are too small — the
        // selector must pick the larger one, Up.
        $state = (new StateBuilder())
            ->size(5, 5)
            ->snake('us', 100, [[0, 2], [1, 2], [2, 2], [3, 2], [4, 2], [4, 1], [4, 0], [3, 0], [3, 1], [2, 1]])
            ->build();

        $move = $this->selector()->select($state);

        self::assertSame(Move::Up, $move);
    }
}
