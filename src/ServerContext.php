<?php

namespace Laravel\Mcp;

class ServerContext
{
    public function __construct(
        public array $supportedProtocolVersions,
        public array $serverCapabilities,
        public string $serverName,
        public string $serverVersion,
        public string $instructions,
        public array $tools,
        public int $maxPaginationLength,
        public int $defaultPaginationLength,
    ) {
    }
}
