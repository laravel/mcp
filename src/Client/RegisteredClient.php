<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Illuminate\Support\Collection;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Cache\PrimitiveCache;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Client\Schema\ToolResult;
use Laravel\Mcp\Exceptions\ClientException;

class RegisteredClient
{
    public function __construct(
        private Client $client,
        private ?PrimitiveCache $cache,
    ) {}

    /**
     * @return Collection<string, Tool>
     */
    public function tools(): Collection
    {
        if ($this->cache instanceof PrimitiveCache) {
            $cached = $this->cache->get('tools');

            if (is_array($cached)) {
                try {
                    return $this->hydrateTools($cached);
                } catch (ClientException) {
                    $this->cache->flush('tools');
                }
            }
        }

        $payloads = $this->client->fetchToolPayloads();
        $tools = $this->hydrateTools($payloads);

        $this->cache?->put('tools', $payloads);

        return $tools;
    }

    public function flushCache(): void
    {
        $this->cache?->flushAll();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function callTool(string $name, array $arguments = []): ToolResult
    {
        return $this->client->callTool($name, $arguments);
    }

    public function ping(): void
    {
        $this->client->ping();
    }

    public function connect(): static
    {
        $this->client->connect();

        return $this;
    }

    public function disconnect(): void
    {
        $this->client->disconnect();
    }

    public function connected(): bool
    {
        return $this->client->connected();
    }

    public function withTimeout(float $seconds): static
    {
        $this->client->withTimeout($seconds);

        return $this;
    }

    public function initializeResult(): ?InitializeResult
    {
        return $this->client->initializeResult();
    }

    /**
     * @param  array<int, mixed>  $payloads
     * @return Collection<string, Tool>
     */
    private function hydrateTools(array $payloads): Collection
    {
        return collect($payloads)->mapWithKeys(function (mixed $payload): array {
            if (! is_array($payload)) {
                throw new ClientException('Invalid tool payload.');
            }

            $tool = Tool::from($this->client, $payload);

            return [$tool->name => $tool];
        });
    }
}
