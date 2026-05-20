<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;
use App\Domain\Move;

/**
 * Implements Phase 5 of /spec/features/move-strategy.md.
 *
 * Produces the set of immediately-survivable Moves — those whose destination
 * is in bounds, not occupied by any Snake's body (lazy: including Tails), and
 * not contestable by a strictly longer Opponent's possible move. It does not
 * select a Move; Phase 6 ({@see SpaceEvaluator}) arbitrates over the set.
 */
final class SurvivalFilter
{
    /**
     * @return list<Move>
     */
    public function survivableMoves(GameState $state): array
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
