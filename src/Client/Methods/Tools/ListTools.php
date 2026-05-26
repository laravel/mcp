<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Tools;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Cache\PrimitiveCache;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Exceptions\ClientException;

/**
 * @implements Method<Collection<string, Tool>>
 */
class ListTools implements Method
{
    public function __construct(
        protected Client $client,
        protected ?PrimitiveCache $cache = null,
        protected ?string $cursor = null,
        protected ?int $limit = null,
    ) {
        //
    }

    public function method(): string
    {
        return 'tools/list';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->cursor === null ? [] : ['cursor' => $this->cursor];
    }

    /**
     * @return Collection<string, Tool>
     */
    public function handle(Protocol $protocol): Collection
    {
        try {
            return $this->hydrate($this->loadPayloads($protocol));
        } catch (ClientException $clientException) {
            if (! $this->cache instanceof PrimitiveCache) {
                throw $clientException;
            }

            $this->cache->flush();

            return $this->hydrate($this->loadPayloads($protocol));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadPayloads(Protocol $protocol): array
    {
        if ($this->cache instanceof PrimitiveCache && $this->limit === null) {
            return $this->cache->remember('tools', fn (): array => $this->fetch($protocol));
        }

        return $this->fetch($protocol);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return Collection<string, Tool>
     */
    protected function hydrate(array $payloads): Collection
    {
        return collect($payloads)->mapWithKeys(function (array $payload): array {
            $tool = Tool::from($this->client, $payload);

            return [$tool->name => $tool];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(Protocol $protocol): array
    {
        if ($this->limit === 0) {
            return [];
        }

        if ($this->limit !== null && $this->limit < 0) {
            throw new ClientException('Tool list limit must be greater than or equal to zero.');
        }

        $payloads = [];
        $cursor = $this->cursor;
        $seenCursors = [];

        while (true) {
            if ($cursor !== null) {
                if (isset($seenCursors[$cursor])) {
                    throw new ClientException("Repeated tools/list cursor [{$cursor}] received from server.");
                }

                $seenCursors[$cursor] = true;
            }

            $result = $protocol->dispatch(new self($this->client, null, $cursor, $this->limit));
            $page = Arr::get($result, 'tools');

            if (! is_array($page)) {
                throw new ClientException('Invalid tools/list response from server.');
            }

            foreach ($page as $payload) {
                if (! is_array($payload)) {
                    throw new ClientException('Invalid tool payload from server.');
                }

                if ($this->limit !== null && count($payloads) >= $this->limit) {
                    return $payloads;
                }

                $payloads[] = $payload;
            }

            $next = Arr::get($result, 'nextCursor');

            if ($next === null || $next === '') {
                return $payloads;
            }

            if (! is_string($next)) {
                throw new ClientException('Invalid tools/list cursor from server.');
            }

            $cursor = $next;
        }
    }
}
