<?php
use Laravel\Mcp\Facades\Mcp;

Mcp::web('demo', \App\McpServers\ExampleServer::class)->withoutMiddleware('web');
Mcp::cli('demo', \App\McpServers\ExampleServer::class);
