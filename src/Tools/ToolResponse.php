<?php

namespace Laravel\Mcp\Tools;

class ToolResponse
{
    /**
     * The text of the response.
     */
    private string $text;

    /**
     * Whether the response is an error.
     */
    private bool $isError;

    /**
     * Create a new tool response.
     */
    public function __construct(string $text, bool $isError = false)
    {
        $this->text = $text;
        $this->isError = $isError;
    }

    /**
     * Convert the response to an array.
     */
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
