<?php

declare(strict_types=1);

namespace Laravel\Mcp;

final readonly class Implementation
{
    /**
     * @param  array<int, array<string, mixed>>  $icons
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
            icons: is_array($icons) ? array_values(array_filter($icons, 'is_array')) : [],
            websiteUrl: is_string($info['websiteUrl'] ?? null) ? $info['websiteUrl'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'icons' => $this->icons === [] ? null : $this->icons,
            'websiteUrl' => $this->websiteUrl,
        ], fn (mixed $value): bool => $value !== null);
    }
}
