<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Resources\Content;

use Laravel\Mcp\Server\Contracts\Resources\Content;

class Text implements Content
{
    public function __construct(public readonly string $content) {}

    public function toArray(): array
    {
        return [
            'text' => $this->content,
        ];
    }
}
