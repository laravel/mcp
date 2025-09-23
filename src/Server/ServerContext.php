<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;

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
    public function tools(Request $request): Collection
    {
        $items = collect($this->tools)->map(fn (Tool|string $toolClass) => is_string($toolClass)
            ? Container::getInstance()->make($toolClass)
            : $toolClass
        )->filter(fn (Tool $tool): bool => $tool->eligibleForRegistration($request));

        $evaluator = new \Laravel\Mcp\Server\Auth\AuthorizationEvaluator;

        return $items->filter(fn (Tool $tool): bool => $evaluator->evaluate($tool, $request)['allowed']);
    }

    public function findToolByName(Request $request, string $name): ?Tool
    {
        return collect($this->tools)
            ->map(fn (Tool|string $toolClass) => is_string($toolClass) ? Container::getInstance()->make($toolClass) : $toolClass)
            ->filter(fn (Tool $tool): bool => $tool->eligibleForRegistration($request))
            ->first(fn (Tool $tool): bool => $tool->name() === $name);
    }

    /**
     * @return Collection<int, Resource>
     */
    public function resources(Request $request): Collection
    {
        $items = collect($this->resources)->map(
            fn (Resource|string $resourceClass) => is_string($resourceClass)
                ? Container::getInstance()->make($resourceClass)
                : $resourceClass
        )->filter(fn (Resource $tool): bool => $tool->eligibleForRegistration($request));

        $evaluator = new \Laravel\Mcp\Server\Auth\AuthorizationEvaluator;

        return $items->filter(fn (Resource $resource): bool => $evaluator->evaluate($resource, $request)['allowed']);
    }

    public function findResourceByUri(Request $request, string $uri): ?Resource
    {
        return collect($this->resources)
            ->map(fn (Resource|string $resourceClass) => is_string($resourceClass) ? Container::getInstance()->make($resourceClass) : $resourceClass)
            ->filter(fn (Resource $resource): bool => $resource->eligibleForRegistration($request))
            ->first(fn (Resource $resource): bool => $resource->uri() === $uri);
    }

    /**
     * @return Collection<int, Prompt>
     */
    public function prompts(Request $request): Collection
    {
        $items = collect($this->prompts)->map(
            fn ($promptClass) => is_string($promptClass)
                ? Container::getInstance()->make($promptClass)
                : $promptClass
        )->filter(fn (Prompt $prompt): bool => $prompt->eligibleForRegistration($request));

        $evaluator = new \Laravel\Mcp\Server\Auth\AuthorizationEvaluator;

        return $items->filter(fn (Prompt $prompt): bool => $evaluator->evaluate($prompt, $request)['allowed']);
    }

    public function findPromptByName(Request $request, string $name): ?Prompt
    {
        return collect($this->prompts)
            ->map(fn (Prompt|string $promptClass) => is_string($promptClass) ? Container::getInstance()->make($promptClass) : $promptClass)
            ->filter(fn (Prompt $prompt): bool => $prompt->eligibleForRegistration($request))
            ->first(fn (Prompt $prompt): bool => $prompt->name() === $name);
    }

    public function perPage(?int $requestedPerPage = null): int
    {
        return min($requestedPerPage ?? $this->defaultPaginationLength, $this->maxPaginationLength);
    }
}
