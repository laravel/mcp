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
        private array $resources,
    ) {}

    /**
     * @return Collection<int, \Laravel\Mcp\Tools\Tool>
     */
    public function tools(): Collection
    {
        return collect($this->tools)
            ->map(fn ($toolClass) => is_string($toolClass) ? app($toolClass) : $toolClass);
    }

    /**
     * @return Collection<int, \Laravel\Mcp\Resources\Resource>
     */
    public function resources(): Collection
    {
        return collect($this->resources)
            ->map(fn ($resourceClass) => is_string($resourceClass) ? app($resourceClass) : $resourceClass);
    }

    public function perPage(?int $requestedPerPage = null): int
    {
        return min($requestedPerPage ?? $this->defaultPaginationLength, $this->maxPaginationLength);
    }
}
