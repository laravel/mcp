<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Annotations\RequiresAbility;
use Laravel\Mcp\Server\Annotations\RequiresScopes;
use Laravel\Mcp\Server\Resource;

#[RequiresAbility('resources.read')]
#[RequiresScopes(['resources:read'])]
class RequiresAuthResource extends Resource
{
    protected string $description = 'Protected Resource';

    public function handle(): Response
    {
        return Response::text('res');
    }
}
