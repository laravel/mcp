<?php

declare(strict_types=1);

namespace Laravel\Mcp\Schema;

use Illuminate\Support\Arr;
use InvalidArgumentException;
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

    public static function from(mixed $data): ?self
    {
        if (! is_array($data)) {
            return null;
        }

        try {
            return new self(
                name: Arr::string($data, 'name'),
                version: Arr::string($data, 'version'),
                title: Arr::string($data, 'title', '') ?: null,
                description: Arr::string($data, 'description', '') ?: null,
                icons: Arr::map(Arr::array($data, 'icons', []), function (mixed $icon): Icon {
                    $icon = Arr::wrap($icon);

                    return Icon::from(
                        src: Arr::string($icon, 'src'),
                        mimeType: Arr::string($icon, 'mimeType', '') ?: null,
                        sizes: array_values(array_filter(Arr::array($icon, 'sizes', []), is_string(...))),
                        theme: IconTheme::tryFrom(Arr::string($icon, 'theme', '')),
                    );
                }),
                websiteUrl: Arr::string($data, 'websiteUrl', '') ?: null,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
