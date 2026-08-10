<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Tools;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Methods\Concerns\PaginatesList;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Support\ToolHeaders;

/**
 * @implements Method<Collection<string, Tool>>
 */
class ListTools implements Method
{
    use PaginatesList;

    public function __construct(
        protected ?Client $client = null,
        ?string $cursor = null,
        ?int $limit = null,
    ) {
        $this->cursor = $cursor;
        $this->limit = $limit;
    }

    protected function listType(): string
    {
        return 'tools';
    }

    protected function nextPage(?string $cursor): static
    {
        return new static($this->client, $cursor, $this->limit);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return Collection<string, Tool>
     */
    protected function hydrate(array $payloads): Collection
    {
        return collect($payloads)
            ->map(fn (array $payload): Tool => Tool::from($this->client, $payload))
            ->reject(function (Tool $tool): bool {
                $reason = ToolHeaders::invalid($tool->inputSchema);

                if ($reason === null) {
                    return false;
                }

                Log::warning("Excluded the MCP tool [{$tool->name}] from the tool list because {$reason}.");

                return true;
            })
            ->mapWithKeys(fn (Tool $tool): array => [$tool->name => $tool]);
    }
}
