<?php

declare(strict_types=1);

namespace Laravel\Mcp\Resources\Results;

use Laravel\Mcp\Contracts\Resources\Content;

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
