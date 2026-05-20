<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\GameStateParser;
use App\Http\MalformedRequestException;
use PHPUnit\Framework\TestCase;

final class GameStateParserTest extends TestCase
{
    private GameStateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new GameStateParser();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function validPayload(): array
    {
        return [
            'game' => [
                'id' => 'game-1',
                'ruleset' => ['name' => 'standard', 'version' => 'v1.0.0'],
                'timeout' => 500,
                'map' => 'standard',
                'source' => 'custom',
            ],
            'turn' => 12,
            'board' => [
                'width' => 11,
                'height' => 11,
                'food' => [
                    ['x' => 1, 'y' => 1],
                    ['x' => 9, 'y' => 9],
                ],
                'hazards' => [],
                'snakes' => [
                    [
                        'id' => 'us',
                        'name' => 'us-name',
                        'health' => 90,
                        'body' => [
                            ['x' => 5, 'y' => 5],
                            ['x' => 5, 'y' => 4],
                            ['x' => 5, 'y' => 3],
                        ],
                        'head' => ['x' => 5, 'y' => 5],
                        'length' => 3,
                        'latency' => '42',
                        'shout' => '',
                        'squad' => '',
                        'customizations' => ['color' => '#888', 'head' => 'default', 'tail' => 'default'],
                    ],
                    [
                        'id' => 'opp',
                        'name' => 'opp-name',
                        'health' => 75,
                        'body' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 0, 'y' => 1],
                        ],
                        'head' => ['x' => 0, 'y' => 0],
                        'length' => 2,
                    ],
                ],
            ],
            'you' => [
                'id' => 'us',
                'name' => 'us-name',
                'health' => 90,
                'body' => [
                    ['x' => 5, 'y' => 5],
                    ['x' => 5, 'y' => 4],
                    ['x' => 5, 'y' => 3],
                ],
                'head' => ['x' => 5, 'y' => 5],
                'length' => 3,
            ],
        ];
    }

    public function testParsesValidStandardPayload(): void
    {
        $state = $this->parser->parse($this->validPayload());

        self::assertSame(12, $state->turn);
        self::assertSame(11, $state->board->width);
        self::assertSame(11, $state->board->height);
        self::assertCount(2, $state->board->food);
        self::assertCount(2, $state->board->snakes);
        self::assertSame('us', $state->you->id);
        self::assertSame(90, $state->you->health);
        self::assertSame(3, $state->you->length());
    }

    public function testIgnoresHazardsAndOtherToleratedFields(): void
    {
        $payload = $this->validPayload();
        $payload['board']['hazards'] = [['x' => 0, 'y' => 0], ['x' => 1, 'y' => 1]];
        $payload['game']['ruleset']['settings'] = ['foodSpawnChance' => 15];

        $state = $this->parser->parse($payload);

        // Parser succeeds; hazards do not appear in the domain Board.
        self::assertCount(2, $state->board->snakes);
    }

    public function testRejectsNonStandardRuleset(): void
    {
        $payload = $this->validPayload();
        $payload['game']['ruleset']['name'] = 'royale';

        $this->expectException(MalformedRequestException::class);
        $this->expectExceptionMessage("Only the 'standard' ruleset is supported");
        $this->parser->parse($payload);
    }

    public function testRejectsMissingRuleset(): void
    {
        $payload = $this->validPayload();
        unset($payload['game']['ruleset']);

        $this->expectException(MalformedRequestException::class);
        $this->parser->parse($payload);
    }

    public function testRejectsHeadMismatchWithBody(): void
    {
        $payload = $this->validPayload();
        $payload['board']['snakes'][0]['head'] = ['x' => 6, 'y' => 6];

        $this->expectException(MalformedRequestException::class);
        $this->expectExceptionMessage('does not match body[0]');
        $this->parser->parse($payload);
    }

    public function testRejectsLengthMismatchWithBodyCount(): void
    {
        $payload = $this->validPayload();
        $payload['board']['snakes'][0]['length'] = 5;

        $this->expectException(MalformedRequestException::class);
        $this->expectExceptionMessage('length 5 does not match body length 3');
        $this->parser->parse($payload);
    }

    public function testRejectsYouNotInBoardSnakes(): void
    {
        $payload = $this->validPayload();
        $payload['you']['id'] = 'ghost';
        $payload['you']['body'][0] = ['x' => 5, 'y' => 5];
        $payload['you']['head'] = ['x' => 5, 'y' => 5];

        $this->expectException(MalformedRequestException::class);
        $this->expectExceptionMessage("`you` (id=ghost) is not present");
        $this->parser->parse($payload);
    }

    public function testRejectsOutOfBoundsFood(): void
    {
        $payload = $this->validPayload();
        $payload['board']['food'][] = ['x' => 11, 'y' => 5];

        $this->expectException(MalformedRequestException::class);
        $this->expectExceptionMessage('out of bounds');
        $this->parser->parse($payload);
    }

    public function testRejectsOutOfBoundsBody(): void
    {
        $payload = $this->validPayload();
        $payload['board']['snakes'][0]['body'][] = ['x' => 11, 'y' => 5];
        $payload['board']['snakes'][0]['length'] = 4;
        $payload['you']['body'][] = ['x' => 11, 'y' => 5];
        $payload['you']['length'] = 4;

        $this->expectException(MalformedRequestException::class);
        $this->parser->parse($payload);
    }

    public function testRejectsMissingTurn(): void
    {
        $payload = $this->validPayload();
        unset($payload['turn']);

        $this->expectException(MalformedRequestException::class);
        $this->expectExceptionMessage("Missing required field 'turn'");
        $this->parser->parse($payload);
    }

    public function testRejectsNonIntegerHealth(): void
    {
        $payload = $this->validPayload();
        $payload['board']['snakes'][0]['health'] = '90';

        $this->expectException(MalformedRequestException::class);
        $this->expectExceptionMessage("Field 'health' must be an integer");
        $this->parser->parse($payload);
    }

    public function testRejectsHealthOutOfDomainRange(): void
    {
        $payload = $this->validPayload();
        $payload['board']['snakes'][0]['health'] = 150;
        $payload['you']['health'] = 150;

        $this->expectException(MalformedRequestException::class);
        $this->parser->parse($payload);
    }

    public function testRejectsNonObjectPayload(): void
    {
        $this->expectException(MalformedRequestException::class);
        $this->parser->parse('not-an-object');
    }

    public function testRejectsEmptySnakesList(): void
    {
        $payload = $this->validPayload();
        $payload['board']['snakes'] = [];

        $this->expectException(MalformedRequestException::class);
        $this->parser->parse($payload);
    }
}
