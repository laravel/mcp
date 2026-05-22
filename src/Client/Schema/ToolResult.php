<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Schema;

use Stringable;

class ToolResult implements Stringable
{
    /**
     * @param  array<int, array<string, mixed>>  $content
     * @param  array<string, mixed>|null  $structuredContent
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public array $content,
        public bool $isError,
        public ?array $structuredContent = null,
        public ?array $meta = null,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function from(array $result): self
    {
        $content = $result['content'] ?? [];
        $isError = $result['isError'] ?? false;
        $structuredContent = $result['structuredContent'] ?? null;
        $meta = $result['_meta'] ?? null;

        return new self(
            content: is_array($content) ? array_values(array_filter($content, is_array(...))) : [],
            isError: is_bool($isError) && $isError,
            structuredContent: is_array($structuredContent) ? $structuredContent : null,
            meta: is_array($meta) ? $meta : null,
        );
    }

    public function text(): string
    {
        $parts = [];

        foreach ($this->content as $item) {
            if (($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)) {
                $parts[] = $item['text'];
            }
        }

        return implode('', $parts);
    }

    public function __toString(): string
    {
        return $this->text();
    }
}
