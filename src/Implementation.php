<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Support\Arr;

final readonly class Implementation
{
    /**
     * @param  list<Icon>  $icons
     */
    public function __construct(
        public string $name,
        public string $version,
        public ?string $title = null,
        public ?string $description = null,
        public array $icons = [],
        public ?string $websiteUrl = null,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Arr::whereNotNull([
            'name' => $this->name,
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'icons' => $this->icons === [] ? null : array_map(fn (Icon $icon): array => $icon->toArray(), $this->icons),
            'websiteUrl' => $this->websiteUrl,
        ]);
    }
}
