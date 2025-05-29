<?php

namespace Laravel\Mcp\Session;

use Laravel\Mcp\SessionContext;

class ArraySessionStore implements SessionStore
{
    protected array $sessions = [];

    public function get(string $id): ?SessionContext
    {
        return $this->sessions[$id] ?? null;
    }

    public function put(string $id, SessionContext $context): void
    {
        $this->sessions[$id] = $context;
    }
}
