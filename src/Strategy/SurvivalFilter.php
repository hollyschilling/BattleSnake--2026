<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;
use App\Domain\Move;

/**
 * Implements Phase 5 of /spec/features/move-strategy.md.
 *
 * Produces two nested Move sets, neither of which selects a Move (Phase 6
 * arbitrates):
 *
 * - {@see openMoves()} — destination in bounds and not a Snake body Cell
 *   (lazy: including Tails). Not *certain* death; may still lose a head-to-head.
 * - {@see survivableMoves()} — the Open Moves that additionally cannot be
 *   contested by a strictly longer Opponent's possible move.
 */
final class SurvivalFilter
{
    /**
     * @return list<Move>
     */
    public function survivableMoves(GameState $state): array
    {
        return $this->movesWhere($state, fn (Coord $dest): bool => $this->isSurvivable($state, $dest));
    }

    /**
     * @return list<Move>
     */
    public function openMoves(GameState $state): array
    {
        return $this->movesWhere($state, fn (Coord $dest): bool => $this->isOpen($state, $dest));
    }

    /**
     * @param callable(Coord): bool $accept
     * @return list<Move>
     */
    private function movesWhere(GameState $state, callable $accept): array
    {
        $head = $state->you->head();
        $moves = [];
        foreach (Move::cases() as $move) {
            if ($accept($head->translate($move))) {
                $moves[] = $move;
            }
        }
        return $moves;
    }

    /**
     * In bounds and not occupied by any Snake's body (v1: including Tails).
     */
    private function isOpen(GameState $state, Coord $destination): bool
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
        return true;
    }

    /**
     * Open, and not a Cell a strictly longer Opponent could move into this Turn.
     */
    private function isSurvivable(GameState $state, Coord $destination): bool
    {
        if (!$this->isOpen($state, $destination)) {
            return false;
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
