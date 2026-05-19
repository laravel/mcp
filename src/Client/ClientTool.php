<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Laravel\Mcp\Client\Exceptions\ClientException;
use Stringable;

class ClientTool implements Stringable
{
    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>|null  $outputSchema
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly array $inputSchema = [],
        public readonly ?array $outputSchema = null,
        public readonly array $annotations = [],
        public readonly array $meta = [],
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $name = $payload['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new ClientException('Invalid tool payload: missing or empty "name".');
        }

        $title = $payload['title'] ?? null;
        $description = $payload['description'] ?? null;
        $inputSchema = $payload['inputSchema'] ?? [];
        $outputSchema = $payload['outputSchema'] ?? null;
        $annotations = $payload['annotations'] ?? [];
        $meta = $payload['_meta'] ?? [];

        return new self(
            name: $name,
            title: is_string($title) ? $title : null,
            description: is_string($description) ? $description : null,
            inputSchema: is_array($inputSchema) ? $inputSchema : [],
            outputSchema: is_array($outputSchema) ? $outputSchema : null,
            annotations: is_array($annotations) ? $annotations : [],
            meta: is_array($meta) ? $meta : [],
        );
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
