<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\SessionContext;

class InitializedServer extends Server
{
    public array $tools = [
        'hello-tool' => ExampleTool::class,
    ];

    public function boot($clientCapabilities = [])
    {
        $context = new SessionContext(
            supportedProtocolVersions: $this->supportedProtocolVersion,
            clientCapabilities: $clientCapabilities,
            serverCapabilities: $this->capabilities,
            serverName: $this->serverName,
            serverVersion: $this->serverVersion,
            instructions: $this->instructions,
            tools: $this->tools
        );

        $context->initialized = true;

        $this->sessionStore->put($this->transport->sessionId(), $context);
    }
}
