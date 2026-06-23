<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Exceptions\ClientException;

trait PaginatesList
{
    protected ?string $cursor = null;

    protected ?int $limit = null;

    /**
     * The plural primitive name used as the list key and method prefix (e.g. 'tools', 'prompts').
     */
    abstract protected function listType(): string;

    /**
     * Build the request for the next page at the given cursor.
     */
    abstract protected function nextPage(?string $cursor): static;

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return Collection<string, mixed>
     */
    abstract protected function hydrate(array $payloads): Collection;

    public function method(): string
    {
        return "{$this->listType()}/list";
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->cursor === null ? [] : ['cursor' => $this->cursor];
    }

    /**
     * @return Collection<string, mixed>
     */
    public function handle(Protocol $protocol): Collection
    {
        return $this->hydrate($this->fetch($protocol));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(Protocol $protocol): array
    {
        $type = $this->listType();
        $singular = Str::singular($type);

        if ($this->limit === 0) {
            return [];
        }

        if ($this->limit !== null && $this->limit < 0) {
            throw new ClientException(ucfirst($singular).' list limit must be greater than or equal to zero.');
        }

        $payloads = [];
        $cursor = $this->cursor;
        $seenCursors = [];

        while (true) {
            if (filled($cursor)) {
                if (isset($seenCursors[$cursor])) {
                    throw new ClientException("Repeated {$type}/list cursor [{$cursor}] received from server.");
                }

                $seenCursors[$cursor] = true;
            }

            $result = $protocol->dispatch($this->nextPage($cursor));
            $page = Arr::get($result, $type);

            if (! is_array($page)) {
                throw new ClientException("Invalid {$type}/list response from server.");
            }

            foreach ($page as $payload) {
                if (! is_array($payload)) {
                    throw new ClientException("Invalid {$singular} payload from server.");
                }

                if ($this->limit !== null && count($payloads) >= $this->limit) {
                    return $payloads;
                }

                $payloads[] = $payload;
            }

            $next = Arr::get($result, 'nextCursor');

            if ($next !== null && ! is_string($next)) {
                throw new ClientException("Invalid {$type}/list cursor from server.");
            }

            if (blank($next)) {
                return $payloads;
            }

            $cursor = $next;
        }
    }
}
