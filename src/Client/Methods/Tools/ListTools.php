<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods\Tools;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Primitives\Tool;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Exceptions\ClientException;

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
        if ($this->limit === 0) {
            return collect();
        }

        if ($this->limit !== null && $this->limit < 0) {
            throw new ClientException('Tool list limit must be greater than or equal to zero.');
        }

        /** @var Collection<string, Tool> $tools */
        $tools = collect();
        $cursor = $this->cursor;
        $seenCursors = [];

        while (true) {
            if ($cursor !== null) {
                if (isset($seenCursors[$cursor])) {
                    throw new ClientException("Repeated tools/list cursor [{$cursor}] received from server.");
                }

                $seenCursors[$cursor] = true;
            }

            $result = $protocol->dispatch(new self($this->client, $cursor, $this->limit));
            $payloads = Arr::get($result, 'tools');

            if (! is_array($payloads)) {
                throw new ClientException('Invalid tools/list response from server.');
            }

            foreach ($payloads as $payload) {
                if (! is_array($payload)) {
                    throw new ClientException('Invalid tool payload from server.');
                }

                if ($this->limit !== null && $tools->count() >= $this->limit) {
                    return $tools;
                }

                $tool = Tool::from($this->client, $payload);
                $tools[$tool->name] = $tool;
            }

            $next = Arr::get($result, 'nextCursor');

            if ($next === null || $next === '') {
                return $tools;
            }

            if (! is_string($next)) {
                throw new ClientException('Invalid tools/list cursor from server.');
            }

            $cursor = $next;
        }
    }
}
