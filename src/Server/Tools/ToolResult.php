<?php

declare(strict_types=1);

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
    protected function __construct(public array $content, public bool $isError = false) {}

    /**
     * Create a new text response.
     */
    public static function text(string $text): self
    {
        return new self([new TextContent($text)]);
    }

    public static function json(array $data): self
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            return static::error(sprintf('Failed to encode data: %s', json_last_error_msg()));
        }

        return static::text($json);
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
