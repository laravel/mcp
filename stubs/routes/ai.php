<?php
use Laravel\Mcp\Facades\Mcp;

Mcp::web('demo', \App\McpServers\ExampleServer::class)->withoutMiddleware('web');
Mcp::local('demo', \App\McpServers\ExampleServer::class);
