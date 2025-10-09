<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Resources\Matching;

use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Resources\Uri;

class UriValidator implements ValidatorInterface
{
    public function matches(Resource $resource, string $uri): bool
    {
        $path = Uri::path($uri);

        return preg_match(Uri::pathRegex($resource->uri())['regex'], rawurldecode($path)) === 1;
    }
}
