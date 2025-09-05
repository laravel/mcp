<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Prompt;

class GoingToFailPrompt extends Prompt
{
    protected string $description = 'This prompt is going to fail validation';

    public function handle(Request $request): void
    {
        $request->validate([
            'should_fail' => 'required|boolean',
        ]);
    }
}
