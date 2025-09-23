<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Annotations\RequiresAbility;
use Laravel\Mcp\Server\Annotations\RequiresScopes;
use Laravel\Mcp\Server\Prompt;

#[RequiresAbility('prompts.read')]
#[RequiresScopes(['prompts:read'])]
class RequiresAuthPrompt extends Prompt
{
    protected string $description = 'Protected Prompt';

    public function handle(): Response
    {
        return Response::text('prompt');
    }
}
