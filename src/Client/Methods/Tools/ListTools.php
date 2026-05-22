<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Tools;

use Illuminate\Support\Collection;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Client\Protocol;

class ListTools implements Method
{
    public function __construct(
        protected Client $client,
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
        /** @var Collection<string, Tool> $tools */
        $tools = collect();
        $cursor = $this->cursor;

        while (true) {
            $result = $protocol->dispatch(new self($this->client, $cursor, $this->limit));

            foreach ($result['tools'] ?? [] as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                $tool = Tool::from($this->client, $payload);
                $tools[$tool->name] = $tool;

                if ($this->limit !== null && $tools->count() >= $this->limit) {
                    return $tools;
                }
            }

            $next = $result['nextCursor'] ?? null;

            if (! is_string($next) || $next === '') {
                return $tools;
            }

            $cursor = $next;
        }
    }
}
