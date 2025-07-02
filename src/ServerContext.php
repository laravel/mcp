<?php

namespace Laravel\Mcp;

class ServerContext
{
    /**
     * Create a new server context instance.
     */
    public function __construct(
        public array $supportedProtocolVersions,
        public array $serverCapabilities,
        public string $serverName,
        public string $serverVersion,
        public string $instructions,
        public array $tools,
        public array $resources,
        public int $maxPaginationLength,
        public int $defaultPaginationLength,
    ) {}
}
