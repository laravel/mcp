<?php

namespace Laravel\Mcp;

class SessionContext
{
    public function __construct(
        public array $supportedProtocolVersions,
        public array $serverCapabilities,
        public string $serverName,
        public string $serverVersion,
        public string $instructions,
        public array $tools,
        public array $clientCapabilities = [],
        public bool $initialized = false
    ) {
    }
}
