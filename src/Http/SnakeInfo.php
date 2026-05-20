<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The configurable subset of {@link https://docs.battlesnake.com/api/example-move GET /} response.
 *
 * `apiversion` is fixed to `"1"` by /spec/interfaces/battlesnake-api.md and is
 * emitted by {@see toWire()} rather than being injected.
 */
final readonly class SnakeInfo
{
    public function __construct(
        public string $author,
        public string $color,
        public string $head,
        public string $tail,
        public string $version,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toWire(): array
    {
        return [
            'apiversion' => '1',
            'author'     => $this->author,
            'color'      => $this->color,
            'head'       => $this->head,
            'tail'       => $this->tail,
            'version'    => $this->version,
        ];
    }
}
