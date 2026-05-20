<?php

declare(strict_types=1);

namespace App\Tests\Http\Controllers;

use App\Domain\GameState;
use App\Domain\Move;
use App\Http\Controllers\MoveController;
use App\Http\GameStateParser;
use App\Strategy\MoveSelector;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MoveControllerTest extends TestCase
{
    /**
     * @return array<array-key, mixed>
     */
    private function validPayload(): array
    {
        return [
            'game' => ['id' => 'g', 'ruleset' => ['name' => 'standard', 'version' => 'v1']],
            'turn' => 0,
            'board' => [
                'width' => 11,
                'height' => 11,
                'food' => [],
                'snakes' => [[
                    'id' => 'us',
                    'health' => 100,
                    'body' => [['x' => 5, 'y' => 5]],
                    'head' => ['x' => 5, 'y' => 5],
                    'length' => 1,
                ]],
            ],
            'you' => [
                'id' => 'us',
                'health' => 100,
                'body' => [['x' => 5, 'y' => 5]],
                'head' => ['x' => 5, 'y' => 5],
                'length' => 1,
            ],
        ];
    }

    private function selectorReturning(Move $move): MoveSelector
    {
        return new class ($move) implements MoveSelector {
            public function __construct(private Move $move)
            {
            }

            public function select(GameState $state): Move
            {
                return $this->move;
            }
        };
    }

    public function testReturnsSelectedMoveForValidPayload(): void
    {
        $controller = new MoveController(new GameStateParser(), $this->selectorReturning(Move::Right));

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/move')
            ->withParsedBody($this->validPayload());
        $response = (new ResponseFactory())->createResponse();

        $result = $controller($request, $response);

        self::assertSame(200, $result->getStatusCode());
        self::assertSame('application/json', $result->getHeaderLine('Content-Type'));
        self::assertSame(['move' => 'right'], json_decode((string) $result->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testReturns400ForMalformedPayload(): void
    {
        $controller = new MoveController(new GameStateParser(), $this->selectorReturning(Move::Up));

        $bad = $this->validPayload();
        $bad['game']['ruleset']['name'] = 'royale';

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/move')
            ->withParsedBody($bad);
        $response = (new ResponseFactory())->createResponse();

        $result = $controller($request, $response);

        self::assertSame(400, $result->getStatusCode());
        self::assertSame('application/json', $result->getHeaderLine('Content-Type'));
        $body = json_decode((string) $result->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $body);
        self::assertStringContainsString("'standard'", $body['error']);
    }

    public function testReturns400ForNonObjectBody(): void
    {
        $controller = new MoveController(new GameStateParser(), $this->selectorReturning(Move::Up));

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/move')
            ->withParsedBody(null);
        $response = (new ResponseFactory())->createResponse();

        $result = $controller($request, $response);

        self::assertSame(400, $result->getStatusCode());
    }
}
