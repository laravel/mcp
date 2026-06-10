<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Primitives;

use Illuminate\Support\Arr;
use Laravel\Mcp\Exceptions\ClientException;

class Resource
{
    /**
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $mimeType,
        public readonly ?int $size,
        public readonly array $annotations,
        public readonly ?array $meta,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): self
    {
        $uri = Arr::get($payload, 'uri');
        $name = Arr::get($payload, 'name');
        $title = Arr::get($payload, 'title');
        $description = Arr::get($payload, 'description');
        $mimeType = Arr::get($payload, 'mimeType');
        $size = Arr::get($payload, 'size');
        $annotations = Arr::get($payload, 'annotations', []);
        $meta = Arr::get($payload, '_meta');

        if (! is_string($uri) || blank($uri)
            || ! is_string($name) || blank($name)
            || ! is_array($annotations)
            || (! is_null($title) && ! is_string($title))
            || (! is_null($description) && ! is_string($description))
            || (! is_null($mimeType) && ! is_string($mimeType))
            || (! is_null($size) && ! is_int($size))
            || (! is_null($meta) && ! is_array($meta))) {
            throw new ClientException('Invalid resource payload from server.');
        }

        return new self(
            uri: $uri,
            name: $name,
            title: $title,
            description: $description,
            mimeType: $mimeType,
            size: $size,
            annotations: $annotations,
            meta: $meta,
        );
    }
}
