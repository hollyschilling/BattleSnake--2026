<?php

declare(strict_types=1);

namespace App\Domain;

enum Move: string
{
    case Up = 'up';
    case Down = 'down';
    case Left = 'left';
    case Right = 'right';

    public function dx(): int
    {
        return match ($this) {
            self::Left => -1,
            self::Right => 1,
            self::Up, self::Down => 0,
        };
    }

    public function dy(): int
    {
        return match ($this) {
            self::Up => 1,
            self::Down => -1,
            self::Left, self::Right => 0,
        };
    }
}
