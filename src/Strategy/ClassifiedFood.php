<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\Coord;

/**
 * A Food Cell classified per Phase 2 of /spec/features/move-strategy.md.
 *
 * `opponentDistance` is `PHP_INT_MAX` and `winningOpponentId` is `null` when no
 * Opponent can reach this Food at all (e.g. solo play, isolated regions).
 */
final readonly class ClassifiedFood
{
    public function __construct(
        public Coord $coord,
        public int $usDistance,
        public int $opponentDistance,
        public ?string $winningOpponentId,
        public bool $isReachable,
        public bool $isWinnable,
        public ?int $winningMargin,
    ) {
    }
}
