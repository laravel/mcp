<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Primitives;

use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Schema\ToolResult;

class Tool
{
    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>|null  $outputSchema
     * @param  array<string, mixed>  $annotations
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        protected Client $client,
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
    public static function from(Client $client, array $payload): self
    {
        $title = $payload['title'] ?? null;
        $description = $payload['description'] ?? null;
        $inputSchema = $payload['inputSchema'] ?? [];
        $outputSchema = $payload['outputSchema'] ?? null;
        $annotations = $payload['annotations'] ?? [];
        $meta = $payload['_meta'] ?? null;

        return new self(
            client: $client,
            name: (string) ($payload['name'] ?? ''),
            title: is_string($title) ? $title : null,
            description: is_string($description) ? $description : null,
            inputSchema: is_array($inputSchema) ? $inputSchema : [],
            outputSchema: is_array($outputSchema) ? $outputSchema : null,
            annotations: is_array($annotations) ? $annotations : [],
            meta: is_array($meta) ? $meta : null,
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call(array $arguments = []): ToolResult
    {
        return $this->client->callTool($this->name, $arguments);
    }
}
