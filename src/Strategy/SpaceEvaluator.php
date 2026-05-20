<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;

/**
 * Implements Phase 6 of /spec/features/move-strategy.md.
 *
 * Measures the enclosed space reachable from a candidate Move's destination
 * Cell and decides whether it is large enough to hold the Snake.
 */
final class SpaceEvaluator
{
    public function assess(GameState $state, Coord $destination): SpaceAssessment
    {
        $width = $state->board->width;
        $height = $state->board->height;

        $obstacles = [];
        foreach ($state->board->snakes as $snake) {
            foreach ($snake->body as $segment) {
                $obstacles[$segment->y * $width + $segment->x] = true;
            }
        }

        $food = [];
        foreach ($state->board->food as $f) {
            $food[$f->y * $width + $f->x] = true;
        }

        $reachableArea = 0;
        $foodInArea = 0;

        $startIdx = $destination->y * $width + $destination->x;
        $visited = [$startIdx => true];
        $frontier = [$startIdx];

        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $cellIdx) {
                $reachableArea++;
                if (isset($food[$cellIdx])) {
                    $foodInArea++;
                }

                $x = $cellIdx % $width;
                $y = intdiv($cellIdx, $width);
                foreach ([[0, 1], [1, 0], [0, -1], [-1, 0]] as [$dx, $dy]) {
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

        $requiredSpace = $state->you->length() + $foodInArea + 1;

        return new SpaceAssessment(
            reachableArea: $reachableArea,
            foodInArea: $foodInArea,
            requiredSpace: $requiredSpace,
            isSafe: $reachableArea >= $requiredSpace,
        );
    }
}
