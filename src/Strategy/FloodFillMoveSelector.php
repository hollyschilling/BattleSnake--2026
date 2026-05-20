<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;
use App\Domain\Move;

/**
 * The full move-strategy pipeline:
 *  Phase 1 — {@see FloodFill}
 *  Phase 2 — {@see FoodClassifier}
 *  Phase 3 — {@see TargetSelector}
 *  Phase 4 — path extraction (inline below)
 *  Phase 5 — {@see SurvivalFilter}
 *  Phase 6 — space-safety arbitration ({@see SpaceEvaluator})
 */
final class FloodFillMoveSelector implements MoveSelector
{
    public function __construct(
        private readonly FloodFill $floodFill,
        private readonly FoodClassifier $foodClassifier,
        private readonly TargetSelector $targetSelector,
        private readonly SurvivalFilter $survivalFilter,
        private readonly SpaceEvaluator $spaceEvaluator,
    ) {
    }

    public function select(GameState $state): Move
    {
        $result = $this->floodFill->run($state);
        $foods = $this->foodClassifier->classify($state, $result);
        $target = $this->targetSelector->selectTarget($state, $result, $foods);

        $candidate = $this->extractFirstMove($state, $result, $target);

        $survivable = $this->survivalFilter->survivableMoves($state);
        if ($survivable === []) {
            return Move::Up; // dead this Turn regardless; engine still needs a Move
        }

        return $this->arbitrateBySpace($state, $survivable, $candidate);
    }

    /**
     * Phase 6 — pick the final Move using {@see SpaceEvaluator}.
     *
     * @param list<Move> $survivable
     */
    private function arbitrateBySpace(GameState $state, array $survivable, ?Move $candidate): Move
    {
        $head = $state->you->head();
        $center = $state->board->center();

        $assessment = [];
        foreach ($survivable as $move) {
            $assessment[$move->value] = $this->spaceEvaluator->assess($state, $head->translate($move));
        }

        // 1. Take the target-path candidate if it is survivable and Space-Safe.
        if ($candidate !== null
            && in_array($candidate, $survivable, true)
            && $assessment[$candidate->value]->isSafe
        ) {
            return $candidate;
        }

        // 2. Otherwise pick the largest-area Move, preferring Space-Safe ones.
        $safe = array_values(array_filter(
            $survivable,
            static fn (Move $m): bool => $assessment[$m->value]->isSafe,
        ));
        $pool = $safe !== [] ? $safe : $survivable;

        $best = $pool[0];
        for ($i = 1, $n = count($pool); $i < $n; $i++) {
            $move = $pool[$i];
            $cmp = $assessment[$move->value]->reachableArea <=> $assessment[$best->value]->reachableArea;
            if ($cmp > 0) {
                $best = $move;
            } elseif ($cmp === 0) {
                $moveDist = $head->translate($move)->manhattanDistanceTo($center);
                $bestDist = $head->translate($best)->manhattanDistanceTo($center);
                if ($moveDist < $bestDist) {
                    $best = $move;
                }
            }
        }

        return $best;
    }

    private function extractFirstMove(GameState $state, FloodFillResult $result, Coord $target): ?Move
    {
        $path = $result->pathFrom($state->you->id, $target);
        if (count($path) < 2) {
            return null;
        }

        $head = $state->you->head();
        $next = $path[1];
        $dx = $next->x - $head->x;
        $dy = $next->y - $head->y;

        return match (true) {
            $dx === 1 && $dy === 0  => Move::Right,
            $dx === -1 && $dy === 0 => Move::Left,
            $dx === 0 && $dy === 1  => Move::Up,
            $dx === 0 && $dy === -1 => Move::Down,
            default                 => null,
        };
    }
}
