<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use Laravel\Mcp\Tests\Fixtures\StreamingTool;

class InitializedServer extends Server
{
    public array $tools = [
        ExampleTool::class,
        StreamingTool::class,
    ];

    public function boot($clientCapabilities = [])
    {
        $session = new SessionContext(
            clientCapabilities: $clientCapabilities,
        );

        $session->initialized = true;

        $this->sessionStore->put($this->transport->sessionId(), $session);
    }
}
