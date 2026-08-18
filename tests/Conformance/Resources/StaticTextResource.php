<?php

declare(strict_types=1);

namespace Tests\Conformance\Resources;

use Laravel\Mcp\Server\Resource;

class StaticTextResource extends Resource
{
    protected string $name = 'static-text';

    protected string $uri = 'test://static-text';

    protected string $mimeType = 'text/plain';

    protected string $description = 'A static text resource for testing';

    public function handle(): string
    {
        return 'This is the content of the static text resource.';
    }
}
