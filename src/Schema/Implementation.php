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
                name: self::stringAt($data, 'name'),
                version: self::stringAt($data, 'version'),
                title: self::stringAt($data, 'title', '') ?: null,
                description: self::stringAt($data, 'description', '') ?: null,
                icons: Arr::map(self::arrayAt($data, 'icons', []), function (mixed $icon): Icon {
                    $icon = Arr::wrap($icon);

                    return Icon::from(
                        src: self::stringAt($icon, 'src'),
                        mimeType: self::stringAt($icon, 'mimeType', '') ?: null,
                        sizes: array_values(array_filter(self::arrayAt($icon, 'sizes', []), is_string(...))),
                        theme: IconTheme::tryFrom(self::stringAt($icon, 'theme', '')),
                    );
                }),
                websiteUrl: self::stringAt($data, 'websiteUrl', '') ?: null,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    protected static function stringAt(array $array, string $key, ?string $default = null): string
    {
        $value = Arr::get($array, $key, $default);

        if (! is_string($value)) {
            throw new InvalidArgumentException("The [{$key}] value must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $array
     * @param  array<array-key, mixed>|null  $default
     * @return array<array-key, mixed>
     */
    protected static function arrayAt(array $array, string $key, ?array $default = null): array
    {
        $value = Arr::get($array, $key, $default);

        if (! is_array($value)) {
            throw new InvalidArgumentException("The [{$key}] value must be an array.");
        }

        return $value;
    }
}
