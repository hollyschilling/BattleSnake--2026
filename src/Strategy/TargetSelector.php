<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;

/**
 * Implements Phase 3 of /spec/features/move-strategy.md.
 */
final class TargetSelector
{
    private const int LOW_HEALTH_THRESHOLD = 20;
    private const int OPPORTUNISTIC_DISTANCE = 2;

    public function __construct(
        private readonly SpaceEvaluator $spaceEvaluator,
        private readonly AggressionEvaluator $aggressionEvaluator,
    ) {
    }

    /**
     * @param list<ClassifiedFood> $foods
     */
    public function selectTarget(GameState $state, FloodFillResult $result, array $foods): Coord
    {
        $center = $state->board->center();

        $opportunistic = $this->selectOpportunistic($state, $foods, $center);
        if ($opportunistic !== null) {
            return $opportunistic;
        }

        if ($state->you->health <= self::LOW_HEALTH_THRESHOLD) {
            return $this->selectLowHealth($result, $foods, $center) ?? $center;
        }

        // Normal health: aggression outranks contested-food chasing.
        $aggressiveMove = $this->aggressionEvaluator->aggressiveMove($state);
        if ($aggressiveMove !== null) {
            return $state->you->head()->translate($aggressiveMove);
        }

        return $this->selectNormalHealth($foods, $center) ?? $center;
    }

    /**
     * A Winnable Food within two Moves whose Cell is Trap-Safe — nearly free
     * to capture, so taken ahead of the health-mode logic.
     *
     * @param list<ClassifiedFood> $foods
     */
    private function selectOpportunistic(GameState $state, array $foods, Coord $center): ?Coord
    {
        $candidates = array_values(array_filter(
            $foods,
            fn (ClassifiedFood $f): bool => $f->isWinnable
                && $f->usDistance <= self::OPPORTUNISTIC_DISTANCE
                && $this->spaceEvaluator->assess($state, $f->coord)->isTrapSafe,
        ));
        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (ClassifiedFood $a, ClassifiedFood $b) use ($center): int {
            $cmp = $a->usDistance <=> $b->usDistance;
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a->coord->manhattanDistanceTo($center) <=> $b->coord->manhattanDistanceTo($center);
        });

        return $candidates[0]->coord;
    }

    /**
     * @param list<ClassifiedFood> $foods
     */
    private function selectNormalHealth(array $foods, Coord $center): ?Coord
    {
        $winnable = array_values(array_filter($foods, static fn (ClassifiedFood $f): bool => $f->isWinnable));
        if ($winnable === []) {
            return null;
        }

        usort($winnable, static function (ClassifiedFood $a, ClassifiedFood $b) use ($center): int {
            $cmp = ($a->winningMargin ?? PHP_INT_MAX) <=> ($b->winningMargin ?? PHP_INT_MAX);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a->coord->manhattanDistanceTo($center) <=> $b->coord->manhattanDistanceTo($center);
        });

        return $winnable[0]->coord;
    }

    /**
     * @param list<ClassifiedFood> $foods
     */
    private function selectLowHealth(FloodFillResult $result, array $foods, Coord $center): ?Coord
    {
        $winnable = array_values(array_filter($foods, static fn (ClassifiedFood $f): bool => $f->isWinnable));
        if ($winnable !== []) {
            usort($winnable, static function (ClassifiedFood $a, ClassifiedFood $b) use ($center): int {
                $cmp = $a->usDistance <=> $b->usDistance;
                if ($cmp !== 0) {
                    return $cmp;
                }
                return $a->coord->manhattanDistanceTo($center) <=> $b->coord->manhattanDistanceTo($center);
            });
            return $winnable[0]->coord;
        }

        $reachable = array_values(array_filter($foods, static fn (ClassifiedFood $f): bool => $f->isReachable));
        if ($reachable === []) {
            return null;
        }

        usort($reachable, static function (ClassifiedFood $a, ClassifiedFood $b) use ($result, $center): int {
            $aTerr = $a->winningOpponentId === null ? 0 : $result->territorySize($a->winningOpponentId);
            $bTerr = $b->winningOpponentId === null ? 0 : $result->territorySize($b->winningOpponentId);
            $cmp = $aTerr <=> $bTerr;
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a->coord->manhattanDistanceTo($center) <=> $b->coord->manhattanDistanceTo($center);
        });

        return $reachable[0]->coord;
    }
}
