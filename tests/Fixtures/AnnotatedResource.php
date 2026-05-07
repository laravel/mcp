<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Server\Annotations\Audience;
use Laravel\Mcp\Server\Annotations\LastModified;
use Laravel\Mcp\Server\Annotations\Priority;
use Laravel\Mcp\Server\Resource;

#[Audience([Role::User])]
#[Priority(0.7)]
#[LastModified('2026-05-01T00:00:00Z')]
class AnnotatedResource extends Resource
{
    protected string $uri = 'file://resources/annotated';

    protected string $mimeType = 'text/plain';

    public function handle(): string
    {
        return 'annotated content';
    }
}
