<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Resources\Matching;

use Laravel\Mcp\Server\Resource;

interface ValidatorInterface
{
    public function matches(Resource $resource, string $uri): bool;
}
