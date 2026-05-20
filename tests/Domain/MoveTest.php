<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Move;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoveTest extends TestCase
{
    /**
     * @return iterable<string, array{Move, int, int}>
     */
    public static function deltaProvider(): iterable
    {
        yield 'up'    => [Move::Up,    0,  1];
        yield 'down'  => [Move::Down,  0, -1];
        yield 'left'  => [Move::Left, -1,  0];
        yield 'right' => [Move::Right, 1,  0];
    }

    #[DataProvider('deltaProvider')]
    public function testDeltas(Move $move, int $expectedDx, int $expectedDy): void
    {
        self::assertSame($expectedDx, $move->dx());
        self::assertSame($expectedDy, $move->dy());
    }

    public function testSerializesToWireValue(): void
    {
        self::assertSame('up', Move::Up->value);
        self::assertSame('down', Move::Down->value);
        self::assertSame('left', Move::Left->value);
        self::assertSame('right', Move::Right->value);
    }
}
