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
        public int $maxPaginationLength,
        public int $defaultPaginationLength,
        public array $clientCapabilities = [],
        public bool $initialized = false
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
