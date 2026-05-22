<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server;

class UriTemplateTestServer extends Server
{
    protected array $resources = [
        UriTemplateSummaryResource::class,
        UriTemplateUserFileResource::class,
    ];
}
