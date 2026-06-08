<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Schema;

use Illuminate\Support\Arr;
use Laravel\Mcp\Exceptions\ClientException;
use Stringable;

class PromptResult implements Stringable
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public array $messages,
        public ?string $description = null,
        public ?array $meta = null,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function from(array $result): self
    {
        $messages = Arr::get($result, 'messages', []);
        $description = Arr::get($result, 'description');
        $meta = Arr::get($result, '_meta');

        if (! is_array($messages)
            || (! is_null($description) && ! is_string($description))) {
            throw new ClientException('Invalid prompts/get result from server.');
        }

        return new self(
            messages: array_values(array_filter($messages, is_array(...))),
            description: $description,
            meta: is_array($meta) ? $meta : null,
        );
    }

    public function text(): string
    {
        $parts = [];

        foreach ($this->messages as $message) {
            $content = Arr::get($message, 'content');

            if (! is_array($content)) {
                continue;
            }

            $text = Arr::get($content, 'text');

            if (Arr::get($content, 'type') === 'text' && is_string($text)) {
                $parts[] = $text;
            }
        }

        return implode('', $parts);
    }

    public function __toString(): string
    {
        return $this->text();
    }
}
