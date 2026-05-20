<?php

declare(strict_types=1);

namespace App\Strategy;

/**
 * The result of evaluating the enclosed space behind a candidate Move,
 * per Phase 6 of /spec/features/move-strategy.md.
 *
 * `reachableArea` ignores Opponent movement; `guaranteedArea` additionally
 * blocks every Cell an Opponent Head could move into next Turn, so it is the
 * space retained even if an Opponent moves to seal us in. `guaranteedArea` is
 * therefore always ≤ `reachableArea`, and `isTrapSafe` implies `isSpaceSafe`.
 */
final readonly class SpaceAssessment
{
    public function __construct(
        public int $reachableArea,
        public int $guaranteedArea,
        public int $foodInArea,
        public int $requiredSpace,
        public bool $isSpaceSafe,
        public bool $isTrapSafe,
    ) {
    }
}
