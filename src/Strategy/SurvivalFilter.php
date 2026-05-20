<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;
use App\Domain\Move;

/**
 * Implements Phase 5 of /spec/features/move-strategy.md.
 *
 * Filters out Moves whose destination is out of bounds, occupied by any Snake's
 * body (lazy: including Tails), or contested by a strictly longer Opponent's
 * possible move. If the candidate Move fails, falls back to the surviving Move
 * with the smallest Center Distance. If no Move survives, returns `Move::Up`
 * (we are dead this Turn regardless).
 */
final class SurvivalFilter
{
    public function filter(GameState $state, ?Move $candidate): Move
    {
        $survivable = $this->survivableMoves($state);

        if ($candidate !== null && in_array($candidate, $survivable, true)) {
            return $candidate;
        }

        if ($survivable === []) {
            return Move::Up;
        }

        $center = $state->board->center();
        $head = $state->you->head();
        $best = $survivable[0];
        $bestDist = $head->translate($best)->manhattanDistanceTo($center);

        for ($i = 1, $n = count($survivable); $i < $n; $i++) {
            $dist = $head->translate($survivable[$i])->manhattanDistanceTo($center);
            if ($dist < $bestDist) {
                $best = $survivable[$i];
                $bestDist = $dist;
            }
        }

        return $best;
    }

    /**
     * @return list<Move>
     */
    private function survivableMoves(GameState $state): array
    {
        $head = $state->you->head();
        $survivable = [];
        foreach (Move::cases() as $move) {
            if ($this->isSurvivable($state, $head->translate($move))) {
                $survivable[] = $move;
            }
        }
        return $survivable;
    }

    private function isSurvivable(GameState $state, Coord $destination): bool
    {
        if (!$state->board->contains($destination)) {
            return false;
        }

        foreach ($state->board->snakes as $snake) {
            foreach ($snake->body as $segment) {
                if ($destination->equals($segment)) {
                    return false;
                }
            }
        }

        $usLength = $state->you->length();
        foreach ($state->board->snakes as $snake) {
            if ($snake->id === $state->you->id) {
                continue;
            }
            if ($snake->length() <= $usLength) {
                continue; // not strictly longer; per spec, ignore for survival filter
            }
            $oppHead = $snake->head();
            foreach (Move::cases() as $oppMove) {
                if ($oppHead->translate($oppMove)->equals($destination)) {
                    return false;
                }
            }
        }

        return true;
    }
}
