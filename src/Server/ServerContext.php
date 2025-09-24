<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;

class ServerContext
{
    /**
     * @param  array<int, string>  $supportedProtocolVersions
     * @param  array<string, mixed>  $serverCapabilities
     * @param  array<int, Tool|string>  $tools
     * @param  array<int, Resource|string>  $resources
     * @param  array<int, Prompt|string>  $prompts
     */
    public function __construct(
        public array $supportedProtocolVersions,
        public array $serverCapabilities,
        public string $serverName,
        public string $serverVersion,
        public string $instructions,
        public int $maxPaginationLength,
        public int $defaultPaginationLength,
        protected array $tools,
        protected array $resources,
        protected array $prompts,
    ) {
        //
    }

    /**
     * @return Collection<int, Tool>
     */
    public function tools(): Collection
    {
        return collect($this->tools)->map(fn (Tool|string $toolClass) => is_string($toolClass)
            ? Container::getInstance()->make($toolClass)
            : $toolClass
        )->filter(fn (Tool $tool): bool => $tool->eligibleForRegistration());
    }

    /**
     * @return Collection<int, Resource>
     */
    public function resources(): Collection
    {
        return collect($this->resources)->map(
            fn (Resource|string $resourceClass) => is_string($resourceClass)
                ? Container::getInstance()->make($resourceClass)
                : $resourceClass
        )->filter(fn (Resource $resource): bool => $resource->eligibleForRegistration());
    }

    /**
     * @return Collection<int, Prompt>
     */
    public function prompts(): Collection
    {
        return collect($this->prompts)->map(
            fn ($promptClass) => is_string($promptClass)
                ? Container::getInstance()->make($promptClass)
                : $promptClass
        )->filter(fn (Prompt $prompt): bool => $prompt->eligibleForRegistration());
    }

    public function perPage(?int $requestedPerPage = null): int
    {
        return min($requestedPerPage ?? $this->defaultPaginationLength, $this->maxPaginationLength);
    }
}
