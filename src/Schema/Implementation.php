<?php

declare(strict_types=1);

namespace Laravel\Mcp\Schema;

use Illuminate\Support\Arr;

class Implementation
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

    /**
     * @param  array{name: string, version: string, title?: mixed, description?: mixed, icons?: mixed, websiteUrl?: mixed}  $info
     */
    public static function fromArray(array $info): self
    {
        $icons = $info['icons'] ?? [];

        return new self(
            name: $info['name'],
            version: $info['version'],
            title: is_string($info['title'] ?? null) ? $info['title'] : null,
            description: is_string($info['description'] ?? null) ? $info['description'] : null,
            icons: is_array($icons) ? array_values(array_filter(array_map(
                fn (mixed $icon): ?Icon => is_array($icon) && is_string($icon['src'] ?? null)
                    ? new Icon(
                        src: $icon['src'],
                        mimeType: is_string($icon['mimeType'] ?? null) ? $icon['mimeType'] : null,
                        sizes: is_array($icon['sizes'] ?? null) ? array_values(array_filter($icon['sizes'], 'is_string')) : [],
                    )
                    : null,
                $icons,
            ))) : [],
            websiteUrl: is_string($info['websiteUrl'] ?? null) ? $info['websiteUrl'] : null,
        );
    }
}
