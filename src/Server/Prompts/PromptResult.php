<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Prompts;

use Illuminate\Contracts\Support\Arrayable;

class PromptResult implements Arrayable
{
    public function __construct(protected string $content, protected string $description) {}

    public function toArray()
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
