<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Prompts;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class PromptResult implements Arrayable
{
    public function __construct(protected string $content, protected string $description)
    {

    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $this->content,
                    ],
                ],
            ],
        ];
    }
}
