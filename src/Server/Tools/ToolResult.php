<?php

namespace Laravel\Mcp\Server\Tools;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Contracts\Tools\Content;

class ToolResult implements Arrayable
{
    /**
     * Create a new tool response.
     *
     * @param  array<Content>  $content
     */
    private function __construct(public readonly array $content, public readonly bool $isError = false) {}

    /**
     * Create a new text response.
     */
    public static function text(string $text): self
    {
        return new self([new TextContent($text)]);
    }

    /**
     * Create a new error response.
     */
    public static function error(string $text): self
    {
        return new self([new TextContent($text)], true);
    }

    /**
     * Create a new response from a list of content items.
     */
    public static function items(Content ...$items): self
    {
        return new self($items);
    }

    /**
     * Convert the response to an array.
     */
    public function toArray(): array
    {
        return [
            'content' => collect($this->content)->map(fn (Content $item) => $item->toArray())->all(),
            'isError' => $this->isError,
        ];
    }
}
