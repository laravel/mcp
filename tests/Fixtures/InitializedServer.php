<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Tests\Fixtures\ExampleTool;
use Laravel\Mcp\Tests\Fixtures\StreamingTool;

class InitializedServer extends Server
{
    public array $tools = [
        'hello-tool' => ExampleTool::class,
        'streaming-tool' => StreamingTool::class,
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
            tools: $this->tools,
            maxPaginationLength: 50,
            defaultPaginationLength: 10,
        );

        $context->initialized = true;

        $this->sessionStore->put($this->transport->sessionId(), $context);
    }
}
