<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools;

use Laravel\Mcp\Server\Contracts\Tools\Content;

class TextContent implements Content
{
    public function __construct(public string $text)
    {
        //
    }

    public function toArray(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }
}
