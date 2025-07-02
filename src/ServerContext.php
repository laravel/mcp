<?php

namespace Laravel\Mcp;

use Illuminate\Support\Collection;

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
        public int $maxPaginationLength,
        public int $defaultPaginationLength,
        private array $tools,
    ) {}

    public function tools(): Collection
    {
        return collect($this->tools)
            ->map(fn ($toolClass) => is_string($toolClass) ? app($toolClass) : $toolClass);
    }

    public function perPage(?int $requestedPerPage = null): int
    {
        return min($requestedPerPage ?? $this->defaultPaginationLength, $this->maxPaginationLength);
    }
}
