<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Coord;
use App\Domain\Move;
use PHPUnit\Framework\TestCase;

final class CoordTest extends TestCase
{
    public function testEqualsIsValueBased(): void
    {
        self::assertTrue((new Coord(3, 4))->equals(new Coord(3, 4)));
        self::assertFalse((new Coord(3, 4))->equals(new Coord(4, 3)));
    }

    public function testTranslateAppliesMoveDelta(): void
    {
        $origin = new Coord(5, 5);

        self::assertTrue($origin->translate(Move::Up)->equals(new Coord(5, 6)));
        self::assertTrue($origin->translate(Move::Down)->equals(new Coord(5, 4)));
        self::assertTrue($origin->translate(Move::Left)->equals(new Coord(4, 5)));
        self::assertTrue($origin->translate(Move::Right)->equals(new Coord(6, 5)));
    }

    public function testManhattanDistanceIsSymmetric(): void
    {
        $a = new Coord(1, 2);
        $b = new Coord(4, 6);

        self::assertSame(7, $a->manhattanDistanceTo($b));
        self::assertSame(7, $b->manhattanDistanceTo($a));
    }

    public function testManhattanDistanceToSelfIsZero(): void
    {
        $a = new Coord(7, 7);
        self::assertSame(0, $a->manhattanDistanceTo($a));
    }
}
