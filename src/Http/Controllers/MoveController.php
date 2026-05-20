<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\GameStateParser;
use App\Http\MalformedRequestException;
use App\Strategy\MoveSelector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MoveController
{
    public function __construct(
        private readonly GameStateParser $parser,
        private readonly MoveSelector $selector,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $state = $this->parser->parse($request->getParsedBody());
        } catch (MalformedRequestException $e) {
            $response->getBody()->write(json_encode(
                ['error' => $e->getMessage()],
                JSON_THROW_ON_ERROR,
            ));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $move = $this->selector->select($state);
        $response->getBody()->write(json_encode(
            ['move' => $move->value],
            JSON_THROW_ON_ERROR,
        ));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
