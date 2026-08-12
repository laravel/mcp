<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Tools\ToolsSearch;

class ServerContext
{
    /**
     * @param  array<int, string>  $supportedProtocolVersions
     * @param  array<string, mixed>  $serverCapabilities
     * @param  array<int, Tool|ToolsSearch|string>  $tools
     * @param  array<int, Resource|string>  $resources
     * @param  array<int, Prompt|string>  $prompts
     */
    public function __construct(
        public array $supportedProtocolVersions,
        public array $serverCapabilities,
        public Implementation $implementation,
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
        $hasToolsSearch = collect($this->tools)->contains(fn (Tool|ToolsSearch|string $tool): bool => $tool instanceof ToolsSearch);

        /** @var Collection<int, Tool|string> $tools */
        $tools = collect($this->tools)->flatMap(fn (Tool|ToolsSearch|string $tool): array => $tool instanceof ToolsSearch
            ? $tool->tools()
            : [$tool]);

        $tools = $this->resolvePrimitives($tools);

        if ($hasToolsSearch) {
            $duplicate = $tools->groupBy(fn (Tool $tool): string => $tool->name())->first(fn (Collection $group): bool => $group->count() > 1);

            if ($duplicate instanceof Collection && $duplicate->first() instanceof Tool) {
                throw new InvalidArgumentException("Duplicate server tool name [{$duplicate->first()->name()}].");
            }
        }

        return $tools;
    }

    /**
     * @return Collection<int, Resource>
     */
    public function resources(): Collection
    {
        /** @var Collection<int,Resource> $resourceTemplates */
        $resourceTemplates = collect($this->resources)
            ->filter(fn (Resource|string $resource): bool => ! $this->isResourceTemplate($resource));

        return $this->resolvePrimitives($resourceTemplates);
    }

    /**
     * @return Collection<int, HasUriTemplate&Resource>
     */
    public function resourceTemplates(): Collection
    {
        /** @var Collection<int,HasUriTemplate&Resource> $resourceTemplates */
        $resourceTemplates = collect($this->resources)
            ->filter(fn (Resource|string $resource): bool => $this->isResourceTemplate($resource));

        return $this->resolvePrimitives($resourceTemplates);
    }

    /**
     * @return Collection<int, Prompt>
     */
    public function prompts(): Collection
    {
        /** @var Collection<int,Prompt> $prompts */
        $prompts = collect($this->prompts);

        return $this->resolvePrimitives($prompts);
    }

    public function perPage(?int $requestedPerPage = null): int
    {
        return min($requestedPerPage ?? $this->defaultPaginationLength, $this->maxPaginationLength);
    }

    public function hasCapability(string $capability): bool
    {
        return array_key_exists($capability, $this->serverCapabilities);
    }

    /**
     * @template T of Primitive
     *
     * @param  Collection<int, T|string>  $primitive
     * @return Collection<int, T>
     */
    private function resolvePrimitives(Collection $primitive): Collection
    {
        return $primitive->map(fn (Primitive|string $primitiveClass) => is_string($primitiveClass)
                ? Container::getInstance()->make($primitiveClass)
                : $primitiveClass)
            ->filter(fn (Primitive $primitive): bool => $primitive->eligibleForRegistration());
    }

    private function isResourceTemplate(Resource|string $resource): bool
    {
        return $resource instanceof HasUriTemplate || (is_string($resource) && is_subclass_of($resource, HasUriTemplate::class));
    }
}
