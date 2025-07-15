<?php

declare(strict_types=1);

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\PromptResult;

class ReviewMyCodePrompt extends Prompt
{
    protected string $description = 'Instructions for how to review my code';

    public function handle(array $arguments): PromptResult
    {
        return new PromptResult(
            content: 'Here are the instructions on how to review my code',
            description: $this->description()
        );
    }
}
