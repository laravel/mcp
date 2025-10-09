<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Resources\Matching;

use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Resources\Uri;

class SchemeValidator implements ValidatorInterface
{
    public function matches(Resource $resource, string $uri): bool
    {
        return $resource->getUriScheme() === Uri::scheme($uri);
    }
}
