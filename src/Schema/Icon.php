<?php

declare(strict_types=1);

namespace Laravel\Mcp\Schema;

use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\IconTheme;

class Icon
{
    /**
     * @param  list<string>  $sizes
     */
    public function __construct(
        public string $src,
        public ?string $mimeType = null,
        public array $sizes = [],
        public ?IconTheme $theme = null,
    ) {}

    /**
     * @param  list<string>  $sizes
     */
    public static function from(string $src, ?string $mimeType = null, array $sizes = [], ?IconTheme $theme = null): self
    {
        return parse_url($src, PHP_URL_SCHEME) !== null
            ? new self($src, $mimeType, $sizes, $theme)
            : new self(asset($src), $mimeType, $sizes, $theme);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Arr::whereNotNull([
            'src' => $this->src,
            'mimeType' => $this->mimeType,
            'sizes' => $this->sizes === [] ? null : $this->sizes,
            'theme' => $this->theme?->value,
        ]);
    }
}
