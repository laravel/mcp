<?php

namespace Laravel\Mcp\Tools;

class ToolResponse
{
    private string $text;
    private bool $isError;

    public function __construct(string $text, bool $isError = false)
    {
        $this->text = $text;
        $this->isError = $isError;
    }

    public function toArray(): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $this->text,
                ],
            ],
            'isError' => $this->isError,
        ];
    }
}
