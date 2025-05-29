<?php

namespace Laravel\Mcp\Session;

use Laravel\Mcp\SessionContext;

interface SessionStore
{
    public function get(string $id): ?SessionContext;

    public function put(string $id, SessionContext $context): void;
}
