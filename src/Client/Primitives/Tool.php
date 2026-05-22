<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Primitives;

use Illuminate\Support\Arr;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Schema\ToolResult;
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
        $name = Arr::get($payload, 'name');

        if (blank($name)) {
            throw new ClientException('Invalid tool payload from server.');
        }

        return new self(
            client: $client,
            name: $name,
            title: Arr::get($payload, 'title'),
            description: Arr::get($payload, 'description'),
            inputSchema: Arr::get($payload, 'inputSchema', []),
            outputSchema: Arr::get($payload, 'outputSchema', []),
            annotations: Arr::get($payload, 'annotations', []),
            meta: Arr::get($payload, '_meta')
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
