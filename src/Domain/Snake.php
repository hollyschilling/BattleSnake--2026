<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final readonly class Snake
{
    /**
     * @param non-empty-list<Coord> $body Head at index 0; Tail at last index.
     */
    public function __construct(
        public string $id,
        public int $health,
        public array $body,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('Snake id must be non-empty.');
        }
        if ($health < 0 || $health > 100) {
            throw new InvalidArgumentException("Snake health must be in [0, 100], got {$health}.");
        }
        if ($body === []) {
            throw new InvalidArgumentException('Snake body must be non-empty.');
        }
    }

    public function head(): Coord
    {
        return $this->body[0];
    }

    public function tail(): Coord
    {
        return $this->body[count($this->body) - 1];
    }

    public function length(): int
    {
        return count($this->body);
    }
}
