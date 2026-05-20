<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;
use App\Domain\GameState;
use App\Domain\Move;
use App\Domain\Snake;

/**
 * Multi-source breadth-first search from every Snake's Head simultaneously.
 *
 * Implements Phase 1 of /spec/features/move-strategy.md.
 *
 * Strategy:
 * 1. Collect all Snake body Cells as obstacles (v1 lazy: tails included).
 * 2. Run a per-Snake BFS from each Head, computing `distances` and `predecessors`.
 *    When a Cell has multiple candidate predecessors at the same BFS depth,
 *    pick the predecessor with the smallest Center Distance; further ties are
 *    broken by the fixed Move ordering `up, right, down, left`.
 * 3. Resolve cross-Snake `owners` by comparing distances per Cell: smallest
 *    distance wins; ties are broken by length (longer wins, equal length →
 *    contested → `null`).
 */
final class FloodFill
{
    /**
     * BFS expansion order. Earlier entries win in predecessor tie-breaks.
     */
    private const array MOVE_ORDER = [Move::Up, Move::Right, Move::Down, Move::Left];

    public function run(GameState $state): FloodFillResult
    {
        $obstacles = $this->collectObstacles($state);
        $center = $state->board->center();

        $distances = [];
        $predecessors = [];

        foreach ($state->board->snakes as $snake) {
            [$distances[$snake->id], $predecessors[$snake->id]] = $this->bfs($state, $snake, $obstacles, $center);
        }

        $owners = $this->resolveOwners($state, $distances);

        return new FloodFillResult(
            width: $state->board->width,
            height: $state->board->height,
            distances: $distances,
            predecessors: $predecessors,
            owners: $owners,
        );
    }

    /**
     * @return array<int, true> set of obstacle Cell indices
     */
    private function collectObstacles(GameState $state): array
    {
        $width = $state->board->width;
        $obstacles = [];
        foreach ($state->board->snakes as $snake) {
            foreach ($snake->body as $segment) {
                $obstacles[$segment->y * $width + $segment->x] = true;
            }
        }
        return $obstacles;
    }

    /**
     * @param array<int, true> $obstacles
     * @return array{0: array<int, int>, 1: array<int, int|null>} [distances, predecessors]
     */
    private function bfs(GameState $state, Snake $source, array $obstacles, Coord $center): array
    {
        $width = $state->board->width;
        $headIdx = $source->head()->y * $width + $source->head()->x;

        $distances = [$headIdx => 0];
        $predecessors = [$headIdx => null];

        $currentLevel = [$headIdx];
        $depth = 0;

        while ($currentLevel !== []) {
            $depth++;
            $candidates = []; // neighbor_idx => list<array{0: int, 1: int}> [predecessor_idx, move_order]

            foreach ($currentLevel as $cellIdx) {
                $cell = new Coord($cellIdx % $width, intdiv($cellIdx, $width));
                foreach (self::MOVE_ORDER as $moveOrder => $move) {
                    $neighbor = $cell->translate($move);
                    if (!$state->board->contains($neighbor)) {
                        continue;
                    }
                    $nIdx = $neighbor->y * $width + $neighbor->x;
                    if (isset($obstacles[$nIdx]) || isset($distances[$nIdx])) {
                        continue;
                    }
                    $candidates[$nIdx][] = [$cellIdx, $moveOrder];
                }
            }

            $nextLevel = [];
            foreach ($candidates as $nIdx => $candList) {
                $bestPredIdx = $candList[0][0];
                $bestMoveOrder = $candList[0][1];
                $bestCenterDist = $this->centerDistance($bestPredIdx, $width, $center);

                for ($i = 1, $n = count($candList); $i < $n; $i++) {
                    [$predIdx, $moveOrder] = $candList[$i];
                    $cDist = $this->centerDistance($predIdx, $width, $center);
                    if ($cDist < $bestCenterDist
                        || ($cDist === $bestCenterDist && $moveOrder < $bestMoveOrder)
                    ) {
                        $bestPredIdx = $predIdx;
                        $bestMoveOrder = $moveOrder;
                        $bestCenterDist = $cDist;
                    }
                }

                $distances[$nIdx] = $depth;
                $predecessors[$nIdx] = $bestPredIdx;
                $nextLevel[] = $nIdx;
            }

            $currentLevel = $nextLevel;
        }

        return [$distances, $predecessors];
    }

    private function centerDistance(int $cellIdx, int $width, Coord $center): int
    {
        $x = $cellIdx % $width;
        $y = intdiv($cellIdx, $width);
        return abs($x - $center->x) + abs($y - $center->y);
    }

    /**
     * @param array<string, array<int, int>> $distances
     * @return array<int, string|null>
     */
    private function resolveOwners(GameState $state, array $distances): array
    {
        $owners = [];
        $bestDist = [];

        $lengths = [];
        foreach ($state->board->snakes as $snake) {
            $lengths[$snake->id] = $snake->length();
        }

        foreach ($state->board->snakes as $snake) {
            $id = $snake->id;
            foreach ($distances[$id] ?? [] as $cellIdx => $d) {
                if (!isset($bestDist[$cellIdx]) || $d < $bestDist[$cellIdx]) {
                    $bestDist[$cellIdx] = $d;
                    $owners[$cellIdx] = $id;
                } elseif ($d === $bestDist[$cellIdx]) {
                    $existing = $owners[$cellIdx];
                    if ($existing === null) {
                        continue; // already contested with equal length
                    }
                    $existingLen = $lengths[$existing];
                    $currentLen = $lengths[$id];
                    if ($currentLen > $existingLen) {
                        $owners[$cellIdx] = $id;
                    } elseif ($currentLen === $existingLen) {
                        $owners[$cellIdx] = null;
                    }
                }
            }
        }

        return $owners;
    }
}
