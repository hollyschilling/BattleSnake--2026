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
 * - {@see openMoves()} — destination in bounds and not an Obstacle Cell
 *   ({@see ObstacleMap} — Vacating Tails are passable). Not *certain* death;
 *   may still lose a head-to-head.
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
        $obstacles = new ObstacleMap($state->board);
        return $this->movesWhere(
            $state,
            fn (Coord $dest): bool => $this->isOpen($state, $obstacles, $dest)
                && !$this->losesHeadToHead($state, $dest),
        );
    }

    /**
     * @return list<Move>
     */
    public function openMoves(GameState $state): array
    {
        $obstacles = new ObstacleMap($state->board);
        return $this->movesWhere(
            $state,
            fn (Coord $dest): bool => $this->isOpen($state, $obstacles, $dest),
        );
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
     * In bounds and not an Obstacle Cell (a Vacating Tail is not an obstacle).
     */
    private function isOpen(GameState $state, ObstacleMap $obstacles, Coord $destination): bool
    {
        return $state->board->contains($destination) && !$obstacles->contains($destination);
    }

    /**
     * True if a longer or equal-length Opponent could move into `$destination`
     * this Turn — a head-to-head that kills us (we lose to a longer Opponent,
     * both die against an equal-length one). See ADR 009.
     */
    private function losesHeadToHead(GameState $state, Coord $destination): bool
    {
        $usLength = $state->you->length();
        foreach ($state->board->snakes as $snake) {
            if ($snake->id === $state->you->id) {
                continue;
            }
            if ($snake->length() < $usLength) {
                continue; // strictly shorter — we win this head-to-head
            }
            $oppHead = $snake->head();
            foreach (Move::cases() as $oppMove) {
                if ($oppHead->translate($oppMove)->equals($destination)) {
                    return true;
                }
            }
        }
        return false;
    }
}
