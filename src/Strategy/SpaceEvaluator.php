<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;

/**
 * Implements Phase 6 of /spec/features/move-strategy.md.
 *
 * Measures the enclosed space reachable from a candidate Move's destination
 * Cell with two flood fills:
 *
 * - the optimistic **Reachable Area**, with only Snake bodies as obstacles;
 * - the pessimistic **Guaranteed Area**, which additionally blocks every free
 *   Cell adjacent to an Opponent Head — the Cells an Opponent could move into
 *   next Turn to seal us in.
 */
final class SpaceEvaluator
{
    private const array NEIGHBORS = [[0, 1], [1, 0], [0, -1], [-1, 0]];

    public function assess(GameState $state, Coord $destination): SpaceAssessment
    {
        $width = $state->board->width;
        $height = $state->board->height;

        $bodies = [];
        foreach ($state->board->snakes as $snake) {
            foreach ($snake->body as $segment) {
                $bodies[$segment->y * $width + $segment->x] = true;
            }
        }

        // Cells an Opponent Head could move into next Turn, on top of bodies.
        $contested = $bodies;
        foreach ($state->board->snakes as $snake) {
            if ($snake->id === $state->you->id) {
                continue;
            }
            $head = $snake->head();
            foreach (self::NEIGHBORS as [$dx, $dy]) {
                $nx = $head->x + $dx;
                $ny = $head->y + $dy;
                if ($nx < 0 || $nx >= $width || $ny < 0 || $ny >= $height) {
                    continue;
                }
                $contested[$ny * $width + $nx] = true;
            }
        }

        $food = [];
        foreach ($state->board->food as $f) {
            $food[$f->y * $width + $f->x] = true;
        }

        $startIdx = $destination->y * $width + $destination->x;

        [$reachableArea, $foodInArea] = $this->flood($startIdx, $width, $height, $bodies, $food);
        [$guaranteedArea] = $this->flood($startIdx, $width, $height, $contested, $food);

        $requiredSpace = $state->you->length() + $foodInArea + 1;

        return new SpaceAssessment(
            reachableArea: $reachableArea,
            guaranteedArea: $guaranteedArea,
            foodInArea: $foodInArea,
            requiredSpace: $requiredSpace,
            isSpaceSafe: $reachableArea >= $requiredSpace,
            isTrapSafe: $guaranteedArea >= $requiredSpace,
        );
    }

    /**
     * Single-source BFS counting reachable free Cells from `$startIdx`. The
     * start Cell always counts, regardless of whether it is an obstacle.
     *
     * @param array<int, true> $obstacles
     * @param array<int, true> $food
     * @return array{0: int, 1: int} [area, foodInArea]
     */
    private function flood(int $startIdx, int $width, int $height, array $obstacles, array $food): array
    {
        $area = 0;
        $foodInArea = 0;
        $visited = [$startIdx => true];
        $frontier = [$startIdx];

        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $cellIdx) {
                $area++;
                if (isset($food[$cellIdx])) {
                    $foodInArea++;
                }

                $x = $cellIdx % $width;
                $y = intdiv($cellIdx, $width);
                foreach (self::NEIGHBORS as [$dx, $dy]) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    if ($nx < 0 || $nx >= $width || $ny < 0 || $ny >= $height) {
                        continue;
                    }
                    $nIdx = $ny * $width + $nx;
                    if (isset($visited[$nIdx]) || isset($obstacles[$nIdx])) {
                        continue;
                    }
                    $visited[$nIdx] = true;
                    $next[] = $nIdx;
                }
            }
            $frontier = $next;
        }

        return [$area, $foodInArea];
    }
}
