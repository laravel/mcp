<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

class ArrayCompletionResponse extends CompletionResponse
{
    /**
     * @param  array<int, string>  $items
     */
    public function __construct(private array $items)
    {
        parent::__construct([]);
    }

    public function resolve(string $value): DirectCompletionResponse
    {
        $filtered = CompletionHelper::filterByPrefix($this->items, $value);

        $hasMore = count($filtered) > self::MAX_VALUES;

        $truncated = array_slice($filtered, 0, self::MAX_VALUES);

        return new DirectCompletionResponse($truncated, $hasMore);
    }
}
