<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Ui;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class Csp implements Arrayable
{
    /**
     * @param  array<int, string>|null  $connectDomains
     * @param  array<int, string>|null  $resourceDomains
     * @param  array<int, string>|null  $frameDomains
     * @param  array<int, string>|null  $baseUriDomains
     */
    public function __construct(
        protected ?array $connectDomains = null,
        protected ?array $resourceDomains = null,
        protected ?array $frameDomains = null,
        protected ?array $baseUriDomains = null,
    ) {
        //
    }

    public static function make(): static
    {
        return new static;
    }

    /**
     * @param  array<int, string>  $domains
     */
    public function connectDomains(array $domains): static
    {
        $this->connectDomains = $domains;

        return $this;
    }

    /**
     * @param  array<int, string>  $domains
     */
    public function resourceDomains(array $domains): static
    {
        $this->resourceDomains = $domains;

        return $this;
    }

    /**
     * @param  array<int, string>  $domains
     */
    public function frameDomains(array $domains): static
    {
        $this->frameDomains = $domains;

        return $this;
    }

    /**
     * @param  array<int, string>  $domains
     */
    public function baseUriDomains(array $domains): static
    {
        $this->baseUriDomains = $domains;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'connectDomains' => $this->connectDomains,
            'resourceDomains' => $this->resourceDomains,
            'frameDomains' => $this->frameDomains,
            'baseUriDomains' => $this->baseUriDomains,
        ], fn (mixed $value): bool => $value !== null);
    }
}
