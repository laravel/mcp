<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Resources;

use Illuminate\Support\Collection;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Methods\Concerns\PaginatesList;
use Laravel\Mcp\Client\Primitives\Resource;

/**
 * @implements Method<Collection<string, Resource>>
 */
class ListResources implements Method
{
    use PaginatesList;

    public function __construct(?string $cursor = null, ?int $limit = null)
    {
        $this->cursor = $cursor;
        $this->limit = $limit;
    }

    protected function listType(): string
    {
        return 'resources';
    }

    protected function nextPage(?string $cursor): static
    {
        return new static($cursor, $this->limit);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return Collection<string, Resource>
     */
    protected function hydrate(array $payloads): Collection
    {
        return collect($payloads)->mapWithKeys(function (array $payload): array {
            $resource = Resource::from($payload);

            return [$resource->uri => $resource];
        });
    }
}
