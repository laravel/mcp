<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Ui;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class UiMeta implements Arrayable
{
    public function __construct(
        protected ?Csp $csp = null,
        protected ?Permissions $permissions = null,
        protected ?string $domain = null,
        protected ?bool $prefersBorder = null,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'csp' => $this->csp?->toArray() ?: null,
            'permissions' => $this->permissions?->toArray() ?: null,
            'domain' => $this->domain,
            'prefersBorder' => $this->prefersBorder,
        ], fn (mixed $value): bool => $value !== null);
    }
}
