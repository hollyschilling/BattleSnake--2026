<?php

declare(strict_types=1);

namespace App\Tests\Http\Controllers;

use App\Http\Controllers\IndexController;
use App\Http\SnakeInfo;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class IndexControllerTest extends TestCase
{
    public function testReturnsInfoAsJson(): void
    {
        $info = new SnakeInfo(
            author: 'holly',
            color: '#3E338F',
            head: 'default',
            tail: 'default',
            version: '0.1.0',
        );
        $controller = new IndexController($info);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller($request, $response);

        self::assertSame(200, $result->getStatusCode());
        self::assertSame('application/json', $result->getHeaderLine('Content-Type'));

        $body = json_decode((string) $result->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([
            'apiversion' => '1',
            'author'     => 'holly',
            'color'      => '#3E338F',
            'head'       => 'default',
            'tail'       => 'default',
            'version'    => '0.1.0',
        ], $body);
    }
}
