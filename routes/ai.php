<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('demo', \App\McpServers\PublicServer::class);
Mcp::local('demo', \App\McpServers\LocalServer::class);
