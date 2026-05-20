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
 */
final class FloodFillMoveSelector implements MoveSelector
{
    public function __construct(
        private readonly FloodFill $floodFill,
        private readonly FoodClassifier $foodClassifier,
        private readonly TargetSelector $targetSelector,
        private readonly SurvivalFilter $survivalFilter,
    ) {
    }

    public function select(GameState $state): Move
    {
        $result = $this->floodFill->run($state);
        $foods = $this->foodClassifier->classify($state, $result);
        $target = $this->targetSelector->selectTarget($state, $result, $foods);

        $candidate = $this->extractFirstMove($state, $result, $target);

        return $this->survivalFilter->filter($state, $candidate);
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
