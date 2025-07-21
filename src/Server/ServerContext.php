<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

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
        private array $prompts,
    ) {}

    /**
     * @return Collection<int, \Laravel\Mcp\Server\Tool>
     */
    public function tools(): Collection
    {
        return collect($this->tools)
            ->map(fn ($toolClass) => is_string($toolClass) ? app($toolClass) : $toolClass)
            ->filter(fn ($tool) => $tool->shouldRegister());
    }

    /**
     * @return Collection<int, \Laravel\Mcp\Server\Resource>
     */
    public function resources(): Collection
    {
        return collect($this->resources)
            ->map(fn ($resourceClass) => is_string($resourceClass) ? app($resourceClass) : $resourceClass);
    }

    /**
     * @return Collection<int, \Laravel\Mcp\Server\Prompt>
     */
    public function prompts(): Collection
    {
        return collect($this->prompts)
            ->map(fn ($promptClass) => is_string($promptClass) ? app($promptClass) : $promptClass);
    }

    public function perPage(?int $requestedPerPage = null): int
    {
        return min($requestedPerPage ?? $this->defaultPaginationLength, $this->maxPaginationLength);
    }
}
