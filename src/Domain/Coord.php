<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Coord
{
    public function __construct(
        public int $x,
        public int $y,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    public function translate(Move $move): self
    {
        return new self($this->x + $move->dx(), $this->y + $move->dy());
    }

    public function manhattanDistanceTo(self $other): int
    {
        return abs($this->x - $other->x) + abs($this->y - $other->y);
    }
}
