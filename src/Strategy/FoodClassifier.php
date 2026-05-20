<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\GameState;

/**
 * Implements Phase 2 of /spec/features/move-strategy.md.
 */
final class FoodClassifier
{
    /**
     * @return list<ClassifiedFood>
     */
    public function classify(GameState $state, FloodFillResult $result): array
    {
        $usId = $state->you->id;
        $usHealth = $state->you->health;
        $usLength = $state->you->length();

        $foods = [];
        foreach ($state->board->food as $food) {
            $usDistance = $result->distance($usId, $food);

            $opponentDistance = PHP_INT_MAX;
            $winningOpponentId = null;
            $winningOpponentLength = 0;

            foreach ($state->board->snakes as $snake) {
                if ($snake->id === $usId) {
                    continue;
                }
                $d = $result->distance($snake->id, $food);
                if ($d < $opponentDistance
                    || ($d === $opponentDistance && $snake->length() > $winningOpponentLength)
                ) {
                    $opponentDistance = $d;
                    $winningOpponentId = $snake->id;
                    $winningOpponentLength = $snake->length();
                }
            }

            $isReachable = $usDistance < $usHealth;
            $isWinnable = false;
            $winningMargin = null;

            if ($isReachable) {
                if ($usDistance < $opponentDistance) {
                    $isWinnable = true;
                    $winningMargin = $opponentDistance === PHP_INT_MAX
                        ? PHP_INT_MAX
                        : $opponentDistance - $usDistance;
                } elseif ($usDistance === $opponentDistance && $usLength > $winningOpponentLength) {
                    $isWinnable = true;
                    $winningMargin = 0;
                }
            }

            $foods[] = new ClassifiedFood(
                coord: $food,
                usDistance: $usDistance,
                opponentDistance: $opponentDistance,
                winningOpponentId: $winningOpponentId,
                isReachable: $isReachable,
                isWinnable: $isWinnable,
                winningMargin: $winningMargin,
            );
        }
        return $foods;
    }
}
