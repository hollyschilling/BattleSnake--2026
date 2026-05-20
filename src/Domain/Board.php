<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final readonly class Board
{
    /**
     * @param list<Coord>           $food
     * @param non-empty-list<Snake> $snakes
     */
    public function __construct(
        public int $width,
        public int $height,
        public array $food,
        public array $snakes,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException("Board dimensions must be positive, got {$width}x{$height}.");
        }
        if ($snakes === []) {
            throw new InvalidArgumentException('Board must contain at least one Snake.');
        }
        foreach ($food as $f) {
            $this->assertInBounds($f, 'food');
        }
        foreach ($snakes as $s) {
            foreach ($s->body as $segment) {
                $this->assertInBounds($segment, "snake {$s->id} body");
            }
        }
    }

    public function center(): Coord
    {
        return new Coord(intdiv($this->width, 2), intdiv($this->height, 2));
    }

    public function contains(Coord $c): bool
    {
        return $c->x >= 0 && $c->x < $this->width
            && $c->y >= 0 && $c->y < $this->height;
    }

    private function assertInBounds(Coord $c, string $context): void
    {
        if (!$this->contains($c)) {
            throw new InvalidArgumentException(
                "Coord ({$c->x}, {$c->y}) out of bounds for {$this->width}x{$this->height} board ({$context})."
            );
        }
    }
}
