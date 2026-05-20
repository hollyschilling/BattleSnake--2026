<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Coord;
use App\Domain\Snake;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SnakeTest extends TestCase
{
    public function testHeadTailAndLengthAreDerivedFromBody(): void
    {
        $snake = new Snake(
            id: 's1',
            health: 80,
            body: [new Coord(1, 1), new Coord(1, 2), new Coord(1, 3)],
        );

        self::assertTrue($snake->head()->equals(new Coord(1, 1)));
        self::assertTrue($snake->tail()->equals(new Coord(1, 3)));
        self::assertSame(3, $snake->length());
    }

    public function testHeadAndTailCanCoincideForSingleSegmentSnake(): void
    {
        $snake = new Snake(id: 's1', health: 100, body: [new Coord(0, 0)]);

        self::assertTrue($snake->head()->equals($snake->tail()));
        self::assertSame(1, $snake->length());
    }

    public function testEmptyIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Snake(id: '', health: 100, body: [new Coord(0, 0)]);
    }

    public function testEmptyBodyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Snake(id: 's1', health: 100, body: []);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidHealthProvider(): iterable
    {
        yield 'negative' => [-1];
        yield 'over max' => [101];
    }

    #[DataProvider('invalidHealthProvider')]
    public function testHealthOutOfRangeIsRejected(int $health): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Snake(id: 's1', health: $health, body: [new Coord(0, 0)]);
    }
}
