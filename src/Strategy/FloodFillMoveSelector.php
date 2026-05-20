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
 *  Phase 6 — space-safety arbitration ({@see SpaceEvaluator}), tiered by
 *            Trap-Safe > Space-Safe-only > neither
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
        if ($survivable !== []) {
            return $this->arbitrateBySpace($state, $survivable, $candidate);
        }

        // No fully-safe Move. Accept a possible head-to-head over a certain
        // wall or body: arbitrate the Open Moves (in bounds, not a body).
        $open = $this->survivalFilter->openMoves($state);
        if ($open !== []) {
            return $this->arbitrateBySpace($state, $open, $candidate);
        }

        // Every Move is certain death — emit a deterministic in-bounds Move.
        return $this->lastResort($state);
    }

    /**
     * All Moves are out of bounds or into a body. Emit the first in-bounds
     * Move so we at least do not gratuitously leave the board.
     */
    private function lastResort(GameState $state): Move
    {
        $head = $state->you->head();
        foreach (Move::cases() as $move) {
            if ($state->board->contains($head->translate($move))) {
                return $move;
            }
        }
        return Move::Up;
    }

    /**
     * Phase 6 — pick the final Move from a set of candidate Moves using
     * {@see SpaceEvaluator}.
     *
     * Tiers the Moves by Trap-Safe > Space-Safe-only > neither, and picks the
     * largest-Guaranteed-Area Move from the highest tier. The set is the
     * Survivable Moves, or — when there are none — the Open Moves.
     *
     * @param non-empty-list<Move> $moves
     */
    private function arbitrateBySpace(GameState $state, array $moves, ?Move $candidate): Move
    {
        $head = $state->you->head();
        $center = $state->board->center();

        $assessment = [];
        foreach ($moves as $move) {
            $assessment[$move->value] = $this->spaceEvaluator->assess($state, $head->translate($move));
        }

        // 1. Take the target-path candidate if it is in the set and Trap-Safe.
        if ($candidate !== null
            && in_array($candidate, $moves, true)
            && $assessment[$candidate->value]->isTrapSafe
        ) {
            return $candidate;
        }

        // 2. Otherwise pick from the highest non-empty tier.
        $trapSafe = array_values(array_filter(
            $moves,
            static fn (Move $m): bool => $assessment[$m->value]->isTrapSafe,
        ));
        $spaceSafeOnly = array_values(array_filter(
            $moves,
            static fn (Move $m): bool => $assessment[$m->value]->isSpaceSafe
                && !$assessment[$m->value]->isTrapSafe,
        ));
        $pool = match (true) {
            $trapSafe !== []      => $trapSafe,
            $spaceSafeOnly !== [] => $spaceSafeOnly,
            default               => $moves,
        };

        // Largest Guaranteed Area, then largest Reachable Area, then nearest Center.
        $best = $pool[0];
        for ($i = 1, $n = count($pool); $i < $n; $i++) {
            $move = $pool[$i];
            $a = $assessment[$move->value];
            $b = $assessment[$best->value];
            $cmp = $a->guaranteedArea <=> $b->guaranteedArea;
            if ($cmp === 0) {
                $cmp = $a->reachableArea <=> $b->reachableArea;
            }
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
