<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Domain\GameState;
use App\Domain\Move;

interface MoveSelector
{
    public function select(GameState $state): Move;
}
