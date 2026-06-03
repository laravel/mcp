<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Primitives;

use Illuminate\Support\Arr;
use Laravel\Mcp\Exceptions\ClientException;

class Tool
{
    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>|null  $outputSchema
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly array $inputSchema,
        public readonly ?array $outputSchema,
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
        $name = Arr::get($payload, 'name');
        $title = Arr::get($payload, 'title');
        $description = Arr::get($payload, 'description');
        $inputSchema = Arr::get($payload, 'inputSchema', []);
        $outputSchema = Arr::get($payload, 'outputSchema');
        $annotations = Arr::get($payload, 'annotations', []);
        $meta = Arr::get($payload, '_meta');

        if (! is_string($name) || blank($name)
            || ! is_array($inputSchema)
            || ! is_array($annotations)
            || (! is_null($title) && ! is_string($title))
            || (! is_null($description) && ! is_string($description))
            || (! is_null($outputSchema) && ! is_array($outputSchema))
            || (! is_null($meta) && ! is_array($meta))) {
            throw new ClientException('Invalid tool payload from server.');
        }

        return new self(
            name: $name,
            title: $title,
            description: $description,
            inputSchema: $inputSchema,
            outputSchema: $outputSchema,
            annotations: $annotations,
            meta: $meta,
        );
    }
}
