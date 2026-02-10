<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ClientTool extends Tool
{
    /** @var array<string, mixed> */
    protected array $inputSchema = [];

    protected Client $client;

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition, Client $client): static
    {
        $tool = new static;

        $tool->name = $definition['name'] ?? '';
        $tool->description = $definition['description'] ?? '';
        $tool->title = $definition['title'] ?? '';
        $tool->inputSchema = $definition['inputSchema'] ?? [];
        $tool->client = $client;

        return $tool;
    }

    public function handle(Request $request): Response
    {
        $result = $this->client->callTool($this->name(), $request->all());

        return Response::text(json_encode($result) ?: '{}');
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return array{
     *     name: string,
     *     title?: string|null,
     *     description?: string|null,
     *     inputSchema?: array<string, mixed>,
     *     annotations?: array<string, mixed>|object,
     *     _meta?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $this->inputSchema !== [] ? $this->inputSchema : (object) [],
            'annotations' => (object) [],
        ];

        // @phpstan-ignore return.type
        return $this->mergeMeta($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function remoteInputSchema(): array
    {
        return $this->inputSchema;
    }
}
