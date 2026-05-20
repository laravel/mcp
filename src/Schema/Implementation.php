<?php

declare(strict_types=1);

namespace Laravel\Mcp\Schema;

use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\IconTheme;

class Implementation
{
    /**
     * @param  array<Icon>  $icons
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
     * @param  array{
     *     name: string,
     *     version: string,
     *     title?: string,
     *     description?: string,
     *     icons?: array<int, array{src: string, mimeType?: string, sizes?: array<string>, theme?: string}>,
     *     websiteUrl?: string,
     * }  $data
     */
    public static function from(array $data): self
    {
        return new self(
            name: Arr::get($data, 'name'),
            version: Arr::get($data, 'version'),
            title: Arr::get($data, 'title'),
            description: Arr::get($data, 'description'),
            icons: Arr::map(Arr::get($data, 'icons', []), fn (array $icon): Icon => Icon::from(
                src: Arr::get($icon, 'src'),
                mimeType: Arr::get($icon, 'mimeType'),
                sizes: Arr::get($icon, 'sizes', []),
                theme: IconTheme::tryFrom(Arr::get($icon, 'theme', '')),
            )),
            websiteUrl: Arr::get($data, 'websiteUrl'),
        );
    }
}
