<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client;

use Illuminate\Support\Collection;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Cache\PrimitiveCache;
use Laravel\Mcp\Client\Methods\Tools\ListTools;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Exceptions\ClientException;

/**
 * @mixin Client
 */
class RegisteredClient
{
    public function __construct(
        private Client $client,
        private ?PrimitiveCache $cache,
    ) {}

    /**
     * @return Collection<string, Tool>
     */
    public function tools(?int $limit = null): Collection
    {
        if ($limit !== null) {
            return $this->client->tools($limit);
        }

        if ($this->cache instanceof PrimitiveCache) {
            $cached = $this->cache->get('tools');

            if (is_array($cached)) {
                try {
                    return ListTools::hydrate($this->client, $cached);
                } catch (ClientException) {
                    $this->cache->flush('tools');
                }
            }
        }

        $payloads = $this->client->fetchToolPayloads();
        $tools = ListTools::hydrate($this->client, $payloads);

        $this->cache?->put('tools', $payloads);

        return $tools;
    }

    public function flushCache(): void
    {
        $this->cache?->flushAll();
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->client->{$method}(...$parameters);

        return $result === $this->client ? $this : $result;
    }
}
