<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Annotations\RequiresAbility;
use Laravel\Mcp\Server\Annotations\RequiresScopes;
use Laravel\Mcp\Server\Tool;

#[RequiresAbility('tools.update')]
#[RequiresScopes(['tools:read'])]
class RequiresAuthTool extends Tool
{
    protected string $description = 'Requires ability and scope';

    public function handle(Request $request): Response
    {
        return Response::text('ok');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
