<?php

declare(strict_types=1);

namespace Laravel\Mcp\Schema;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use RuntimeException;

class Icon
{
    /**
     * @param  list<string>  $sizes
     */
    public function __construct(
        public string $src,
        public ?string $mimeType = null,
        public array $sizes = [],
        public ?string $theme = null,
    ) {
        $scheme = strtolower((string) parse_url($src, PHP_URL_SCHEME));

        if (! in_array($scheme, ['https', 'data'], true)) {
            throw new InvalidArgumentException(
                "Icon src must use https: or data: scheme, got: '{$scheme}'.",
            );
        }

        if ($theme !== null && ! in_array($theme, ['light', 'dark'], true)) {
            throw new InvalidArgumentException("Icon theme must be 'light' or 'dark'.");
        }
    }

    /**
     * @param  list<string>  $sizes
     */
    public static function asset(string $path, ?string $mimeType = null, array $sizes = [], ?string $theme = null): self
    {
        return new self(asset($path), $mimeType, $sizes, $theme);
    }

    /**
     * @param  list<string>  $sizes
     */
    public static function fromFile(string $path, array $sizes = [], ?string $theme = null): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Icon file not found or not readable: {$path}");
        }

        $mime = mime_content_type($path);

        if (! is_string($mime) || $mime === '') {
            $mime = 'application/octet-stream';
        }

        return new self(
            src: 'data:'.$mime.';base64,'.base64_encode($contents),
            mimeType: $mime,
            sizes: $sizes,
            theme: $theme,
        );
    }

    /**
     * @param  list<string>  $sizes
     */
    public static function fromPublic(string $relativePath, array $sizes = [], ?string $theme = null): self
    {
        return self::fromFile(public_path($relativePath), $sizes, $theme);
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
            'theme' => $this->theme,
        ]);
    }
}
