<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Ui;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Ui\Enums\Library;

/**
 * @implements Arrayable<string, mixed>
 */
class AppMeta implements Arrayable
{
    /**
     * @param  array<int, Library>  $libraries
     */
    public function __construct(
        protected ?Csp $csp = null,
        protected ?Permissions $permissions = null,
        protected ?string $domain = null,
        protected ?bool $prefersBorder = true,
        protected array $libraries = [],
    ) {
        //
    }

    public static function make(): static
    {
        return new static;
    }

    public function csp(Csp $csp): static
    {
        $this->csp = $csp;

        return $this;
    }

    public function permissions(Permissions $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function domain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function prefersBorder(bool $prefersBorder = true): static
    {
        $this->prefersBorder = $prefersBorder;

        return $this;
    }

    public function libraries(Library ...$libraries): static
    {
        $this->libraries = $libraries;

        return $this;
    }

    /**
     * @return array<int, Library>
     */
    public function getLibraries(): array
    {
        return $this->libraries;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $cspArray = $this->csp?->toArray() ?: [];

        if ($this->libraries !== []) {
            $libraryDomains = collect($this->libraries)
                ->map(fn(Library $lib) => $lib->domains())
                ->flatten();

            $cspArray['resourceDomains'] = collect($cspArray['resourceDomains'] ?? [])
                ->merge($libraryDomains)
                ->unique()
                ->values()
                ->all();
        }

        return array_filter([
            'csp' => $cspArray ?: null,
            'permissions' => $this->permissions?->toArray() ?: null,
            'domain' => $this->domain,
            'prefersBorder' => $this->prefersBorder,
        ], fn(mixed $value): bool => $value !== null);
    }
}
