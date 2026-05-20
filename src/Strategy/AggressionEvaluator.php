<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Board;
use App\Domain\GameState;
use App\Domain\Move;
use App\Domain\Snake;

/**
 * Implements the Aggression step of Phase 3 — see ADR 010.
 *
 * When healthy and near a Weaker Opponent (one strictly shorter than us),
 * picks the Trap-Safe Move that most shrinks that Opponent's Reachable Area,
 * cutting off its space. Returns `null` when Aggression does not apply or no
 * Move can actually shrink the Opponent.
 */
final class AggressionEvaluator
{
    private const int HEALTH_FLOOR = 20;
    private const int ENGAGEMENT_RANGE = 4;

    public function __construct(
        private readonly SurvivalFilter $survivalFilter,
        private readonly SpaceEvaluator $spaceEvaluator,
    ) {
    }

    public function aggressiveMove(GameState $state): ?Move
    {
        if ($state->you->health <= self::HEALTH_FLOOR) {
            return null;
        }

        $opponent = $this->nearestWeakerOpponent($state);
        if ($opponent === null) {
            return null;
        }

        $candidates = $this->trapSafeMoves($state);
        if ($candidates === []) {
            return null;
        }

        $opponentHead = $opponent->head();
        $bestMove = null;
        $bestArea = $this->spaceEvaluator->reachableAreaFrom($state, $opponentHead);

        foreach ($candidates as $move) {
            $after = $this->stateAfterOurMove($state, $move);
            $area = $this->spaceEvaluator->reachableAreaFrom($after, $opponentHead);
            if ($area < $bestArea) {
                $bestArea = $area;
                $bestMove = $move;
            }
        }

        return $bestMove;
    }

    private function nearestWeakerOpponent(GameState $state): ?Snake
    {
        $head = $state->you->head();
        $usLength = $state->you->length();

        $candidates = [];
        foreach ($state->board->snakes as $snake) {
            if ($snake->id === $state->you->id) {
                continue;
            }
            if ($snake->length() >= $usLength) {
                continue; // not a Weaker Opponent
            }
            if ($head->manhattanDistanceTo($snake->head()) > self::ENGAGEMENT_RANGE) {
                continue;
            }
            $candidates[] = $snake;
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (Snake $a, Snake $b) use ($head): int {
            $cmp = $head->manhattanDistanceTo($a->head()) <=> $head->manhattanDistanceTo($b->head());
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = $a->length() <=> $b->length();
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a->id, $b->id);
        });

        return $candidates[0];
    }

    /**
     * @return list<Move>
     */
    private function trapSafeMoves(GameState $state): array
    {
        $head = $state->you->head();
        $moves = [];
        foreach ($this->survivalFilter->survivableMoves($state) as $move) {
            if ($this->spaceEvaluator->assess($state, $head->translate($move))->isTrapSafe) {
                $moves[] = $move;
            }
        }
        return $moves;
    }

    /**
     * A hypothetical GameState with our Snake advanced by `$move` — the new
     * Head prepended and the Tail dropped, unless the Move eats Food.
     */
    private function stateAfterOurMove(GameState $state, Move $move): GameState
    {
        $us = $state->you;
        $newHead = $us->head()->translate($move);

        $ateFood = false;
        foreach ($state->board->food as $food) {
            if ($food->equals($newHead)) {
                $ateFood = true;
                break;
            }
        }

        $newBody = $ateFood
            ? array_merge([$newHead], $us->body)
            : array_merge([$newHead], array_slice($us->body, 0, -1));
        $newUs = new Snake($us->id, $us->health, $newBody);

        $snakes = [];
        foreach ($state->board->snakes as $snake) {
            $snakes[] = $snake->id === $us->id ? $newUs : $snake;
        }
        $board = new Board(
            width: $state->board->width,
            height: $state->board->height,
            food: $state->board->food,
            snakes: $snakes,
        );

        return new GameState(turn: $state->turn, board: $board, you: $newUs);
    }
}
