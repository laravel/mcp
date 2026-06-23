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
        protected ?Client $client,
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
    public static function from(?Client $client, array $payload): self
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
            client: $client,
            name: $name,
            title: $title,
            description: $description,
            inputSchema: $inputSchema,
            outputSchema: $outputSchema,
            annotations: $annotations,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call(array $arguments = []): ToolResult
    {
        if (! $this->client instanceof Client) {
            throw new ClientException("Tool [{$this->name}] is not bound to a client.");
        }

        return $this->client->callTool($this->name, $arguments);
    }
}
