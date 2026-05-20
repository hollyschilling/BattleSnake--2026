<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;

/**
 * Output of the multi-source Flood Fill.
 *
 * - `distances`    : per-Snake map of Cell-index to BFS depth from that Snake's Head.
 * - `predecessors` : per-Snake map of Cell-index to the predecessor Cell-index on
 *                    the shortest path from the Head, or `null` for the Head itself.
 * - `owners`       : Cell-index to the Snake id that reaches it first
 *                    (tie-broken by length; `null` for contested or unreached Cells).
 *
 * Cell indexing is `y * width + x`.
 */
final readonly class FloodFillResult
{
    /**
     * @param array<string, array<int, int>>      $distances
     * @param array<string, array<int, int|null>> $predecessors
     * @param array<int, string|null>             $owners
     */
    public function __construct(
        public int $width,
        public int $height,
        private array $distances,
        private array $predecessors,
        private array $owners,
    ) {
    }

    public function distance(string $snakeId, Coord $cell): int
    {
        return $this->distances[$snakeId][$this->encode($cell)] ?? PHP_INT_MAX;
    }

    public function owner(Coord $cell): ?string
    {
        return $this->owners[$this->encode($cell)] ?? null;
    }

    public function territorySize(string $snakeId): int
    {
        $count = 0;
        foreach ($this->owners as $owner) {
            if ($owner === $snakeId) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Shortest path from `$snakeId`'s Head to `$target`, inclusive of both ends.
     * Returns an empty list if `$target` is unreachable.
     *
     * @return list<Coord>
     */
    public function pathFrom(string $snakeId, Coord $target): array
    {
        $targetIdx = $this->encode($target);
        if (!isset($this->distances[$snakeId][$targetIdx])) {
            return [];
        }

        $path = [];
        $current = $targetIdx;
        while ($current !== null) {
            $path[] = $this->decode($current);
            $current = $this->predecessors[$snakeId][$current];
        }
        return array_reverse($path);
    }

    private function encode(Coord $c): int
    {
        return $c->y * $this->width + $c->x;
    }

    private function decode(int $idx): Coord
    {
        return new Coord($idx % $this->width, intdiv($idx, $this->width));
    }
}
