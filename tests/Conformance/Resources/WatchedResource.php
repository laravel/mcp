<?php

declare(strict_types=1);

namespace Tests\Conformance\Resources;

use Laravel\Mcp\Server\Resource;

class WatchedResource extends Resource
{
    protected string $name = 'watched-resource';

    protected string $uri = 'test://watched-resource';

    protected string $mimeType = 'text/plain';

    protected string $description = 'A resource that can be watched';

    public function handle(): string
    {
        return 'Watched resource content';
    }
}
