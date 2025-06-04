<?php

namespace Laravel\Mcp\Session;

use Laravel\Mcp\Session\Session;
use Laravel\Mcp\SessionContext;

class DatabaseSessionStore implements SessionStore
{
    public function get(string $id): ?SessionContext
    {
        $sessionModel = Session::find($id);

        return $sessionModel ? SessionContext::fromArray($sessionModel->payload) : null;
    }

    public function put(string $id, SessionContext $context): void
    {
        Session::updateOrCreate(
            ['id' => $id],
            ['payload' => $context->toArray()]
        );
    }
}
