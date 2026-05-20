<?php

declare(strict_types=1);

namespace App\Tests\Http;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Exercises the full Slim middleware stack via bootstrap/app.php — body
 * parsing, routing, controllers, and error handling.
 */
final class RoutingTest extends TestCase
{
    private function app(): App
    {
        return require __DIR__ . '/../../bootstrap/app.php';
    }

    public function testInfoRouteReturnsJson(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app()->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('1', $body['apiversion']);
    }

    public function testMoveRouteParsesBodyAndReturnsMove(): void
    {
        $payload = [
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

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/move')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new StreamFactory())->createStream(json_encode($payload, JSON_THROW_ON_ERROR)));

        $response = $this->app()->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertContains($body['move'], ['up', 'down', 'left', 'right']);
    }

    public function testUnknownRouteReturnsCleanNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/CLAUDE.md');
        $response = $this->app()->handle($request);

        self::assertSame(404, $response->getStatusCode());

        // Regression guard: no PHP fatal / stack trace must leak into the body.
        $body = (string) $response->getBody();
        self::assertStringNotContainsString('Fatal error', $body);
        self::assertStringNotContainsString('Stack trace', $body);
        self::assertStringNotContainsString('/var/www', $body);
    }

    public function testWrongMethodReturnsMethodNotAllowed(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/move');
        $response = $this->app()->handle($request);

        self::assertSame(405, $response->getStatusCode());
    }
}
