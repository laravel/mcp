<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Tools;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Methods\Concerns\PaginatesList;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Exceptions\MirroredParameterException;

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
        $tools = [];

        foreach ($payloads as $payload) {
            try {
                $tool = Tool::from($this->client, $payload);
            } catch (MirroredParameterException $mirroredParameterException) {
                $name = is_string($payload['name'] ?? null) ? $payload['name'] : 'unknown';

                Log::warning("Excluded the MCP tool [{$name}] from the tool list because {$mirroredParameterException->getMessage()}.");

                continue;
            }

            $tools[$tool->name] = $tool;
        }

        return collect($tools);
    }
}
