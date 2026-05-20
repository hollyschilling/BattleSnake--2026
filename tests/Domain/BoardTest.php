<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Board;
use App\Domain\Coord;
use App\Domain\Snake;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BoardTest extends TestCase
{
    private function makeSnake(string $id = 's1', Coord ...$body): Snake
    {
        if ($body === []) {
            $body = [new Coord(5, 5)];
        }
        return new Snake(id: $id, health: 100, body: $body);
    }

    public function testCenterIsFloorOfMidpoint(): void
    {
        $board = new Board(width: 11, height: 11, food: [], snakes: [$this->makeSnake()]);
        self::assertTrue($board->center()->equals(new Coord(5, 5)));

        $smaller = new Board(width: 7, height: 7, food: [], snakes: [
            new Snake(id: 's1', health: 100, body: [new Coord(3, 3)]),
        ]);
        self::assertTrue($smaller->center()->equals(new Coord(3, 3)));
    }

    public function testContainsRespectsBounds(): void
    {
        $board = new Board(width: 11, height: 11, food: [], snakes: [$this->makeSnake()]);

        self::assertTrue($board->contains(new Coord(0, 0)));
        self::assertTrue($board->contains(new Coord(10, 10)));
        self::assertFalse($board->contains(new Coord(-1, 5)));
        self::assertFalse($board->contains(new Coord(5, -1)));
        self::assertFalse($board->contains(new Coord(11, 5)));
        self::assertFalse($board->contains(new Coord(5, 11)));
    }

    public function testRejectsOutOfBoundsFood(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Board(
            width: 11,
            height: 11,
            food: [new Coord(11, 0)],
            snakes: [$this->makeSnake()],
        );
    }

    public function testRejectsOutOfBoundsSnakeBody(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Board(
            width: 11,
            height: 11,
            food: [],
            snakes: [new Snake(id: 's1', health: 100, body: [new Coord(11, 11)])],
        );
    }

    public function testRejectsNonPositiveDimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Board(width: 0, height: 11, food: [], snakes: [$this->makeSnake()]);
    }

    public function testRejectsEmptySnakeList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Board(width: 11, height: 11, food: [], snakes: []);
    }
}
