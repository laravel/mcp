<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\PromptResult;

class TellMeHiPrompt extends Prompt
{
    protected string $description = 'Instructions for how too tell me hi';

    public function handle(): PromptResult
    {
        return new PromptResult(
            content: 'Here are the instructions on how to tell me hi',
            description: $this->description()
        );
    }
}
