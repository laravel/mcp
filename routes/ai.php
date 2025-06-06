<?php
use Laravel\Mcp\Facades\Mcp;

Mcp::web('demo', \App\McpServers\ExampleServer::class);
Mcp::cli('demo', \App\McpServers\ExampleServer::class);
