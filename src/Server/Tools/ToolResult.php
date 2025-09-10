<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Contracts\Tools\Content;

/**
 * @implements Arrayable<string, mixed>
 */
class ToolResult implements Arrayable
{
    /**
     * @param  array<int, Content>  $content
     */
    protected function __construct(public array $content, public bool $isError = false)
    {
        //
    }

    public static function text(string $text): static
    {
        return new static([new TextContent($text)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data): static
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);

        if ($json === false) {
            return static::error(sprintf('Failed to encode data: %s', json_last_error_msg()));
        }

        return static::text($json);
    }

    public static function error(string $text): static
    {
        return new static([new TextContent($text)], true);
    }

    public static function items(Content ...$items): static
    {
        return new static($items);
    }

    public function toArray(): array
    {
        return [
            'content' => collect($this->content)->map(fn (Content $item): array => $item->toArray())->all(),
            'isError' => $this->isError,
        ];
    }
}
