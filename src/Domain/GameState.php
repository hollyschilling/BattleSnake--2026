<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final readonly class GameState
{
    public function __construct(
        public int $turn,
        public Board $board,
        public Snake $you,
    ) {
        if ($turn < 0) {
            throw new InvalidArgumentException("Turn must be non-negative, got {$turn}.");
        }
        foreach ($board->snakes as $s) {
            if ($s->id === $you->id) {
                return;
            }
        }
        throw new InvalidArgumentException("`you` (id={$you->id}) is not present in board.snakes.");
    }
}
