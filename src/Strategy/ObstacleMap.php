<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Board;
use App\Domain\Coord;

/**
 * The set of Obstacle Cells for a Board — every Snake body segment except a
 * Vacating Tail (the Tail of a Snake, length ≥ 2, that did not just eat).
 *
 * Implements the tail-aware obstacle rule of /spec/decisions/008-tail-aware-obstacles.md,
 * shared by the Flood Fill (Phase 1), the Survival Filter (Phase 5), and the
 * Space-Safety check (Phase 6).
 *
 * Cell indexing is `y * width + x`, matching {@see FloodFill} and
 * {@see SpaceEvaluator}.
 */
final class ObstacleMap
{
    /** @var array<int, true> */
    private readonly array $indices;
    private readonly int $width;

    public function __construct(Board $board)
    {
        $width = $board->width;
        $indices = [];

        foreach ($board->snakes as $snake) {
            $body = $snake->body;
            $tailIndex = count($body) - 1;
            $tailVacates = $snake->length() >= 2 && !$snake->justAte();

            foreach ($body as $i => $segment) {
                if ($i === $tailIndex && $tailVacates) {
                    continue; // a Vacating Tail is passable
                }
                $indices[$segment->y * $width + $segment->x] = true;
            }
        }

        $this->indices = $indices;
        $this->width = $width;
    }

    public function contains(Coord $cell): bool
    {
        return isset($this->indices[$cell->y * $this->width + $cell->x]);
    }

    /**
     * @return array<int, true> Obstacle Cell indices, for breadth-first search.
     */
    public function indices(): array
    {
        return $this->indices;
    }
}
