<?php

namespace Laravel\Mcp;

class ServerContext
{
    public function __construct(
        public readonly array $capabilities,
        public readonly string $serverName,
        public readonly string $serverVersion,
        public readonly string $instructions,
        public readonly array $tools
    ) {
    }
} 