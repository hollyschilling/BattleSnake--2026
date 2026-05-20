<?php

declare(strict_types=1);

namespace App\Strategy;

/**
 * The result of evaluating the enclosed space behind a candidate Move,
 * per Phase 6 of /spec/features/move-strategy.md.
 */
final readonly class SpaceAssessment
{
    public function __construct(
        public int $reachableArea,
        public int $foodInArea,
        public int $requiredSpace,
        public bool $isSafe,
    ) {
    }
}
