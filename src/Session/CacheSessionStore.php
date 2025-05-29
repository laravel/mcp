<?php

namespace Laravel\Mcp\Session;

use Laravel\Mcp\SessionContext;

class CacheSessionStore implements SessionStore
{
    public function get(string $id): ?SessionContext
    {
        return cache()->get('mcp.session.' . $id)
            ? SessionContext::fromArray(json_decode(cache()->get('mcp.session.' . $id), true))
            : null;
    }

    public function put(string $id, SessionContext $context): void
    {
        cache()->put('mcp.session.' . $id, json_encode($context->toArray()));
    }
}
