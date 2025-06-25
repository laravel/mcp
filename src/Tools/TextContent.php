<?php

namespace Laravel\Mcp\Tools;

use Laravel\Mcp\Contracts\Tools\Content;

class TextContent implements Content
{
    /**
     * Create a new text content item.
     */
    public function __construct(public readonly string $text)
    {
        //
    }

    /**
     * Convert the content to an array.
     */
    public function toArray(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }
}
