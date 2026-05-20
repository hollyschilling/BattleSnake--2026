<?php

declare(strict_types=1);

/**
 * Builds and returns the configured Slim application.
 *
 * The Composer autoloader must already be loaded by the caller
 * (public/index.php for serving, phpunit for tests).
 */

use App\Http\Controllers\IndexController;
use App\Http\Controllers\MoveController;
use App\Http\GameStateParser;
use App\Http\SnakeInfo;
use App\Strategy\FloodFill;
use App\Strategy\FloodFillMoveSelector;
use App\Strategy\FoodClassifier;
use App\Strategy\SurvivalFilter;
use App\Strategy\TargetSelector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;

$info = new SnakeInfo(
    author: $_ENV['SNAKE_AUTHOR'] ?? 'holly-schilling',
    color: $_ENV['SNAKE_COLOR'] ?? '#3E338F',
    head: $_ENV['SNAKE_HEAD'] ?? 'default',
    tail: $_ENV['SNAKE_TAIL'] ?? 'default',
    version: '0.1.0',
);

$parser = new GameStateParser();
$selector = new FloodFillMoveSelector(
    floodFill: new FloodFill(),
    foodClassifier: new FoodClassifier(),
    targetSelector: new TargetSelector(),
    survivalFilter: new SurvivalFilter(),
);

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->get('/', new IndexController($info));
$app->post('/start', static fn (ServerRequestInterface $req, ResponseInterface $res): ResponseInterface => $res);
$app->post('/move', new MoveController($parser, $selector));
$app->post('/end', static fn (ServerRequestInterface $req, ResponseInterface $res): ResponseInterface => $res);

// Outermost middleware: converts unmatched routes and uncaught exceptions
// into proper HTTP responses. displayErrorDetails=false keeps stack traces
// out of responses; errors are still logged for diagnosis.
$app->addErrorMiddleware(false, true, true);

return $app;
