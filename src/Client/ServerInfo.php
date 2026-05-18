<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

final readonly class ServerInfo
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
}
