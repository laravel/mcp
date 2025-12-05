<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

final class ListCompletionResult extends CompletionResult
{
    /**
     * @param  array<int, string>  $items
     */
    public function __construct(private array $items)
    {
        parent::__construct([]);
    }

    public function resolve(string $value): DirectCompletionResult
    {
        $filtered = CompletionHelper::filterByPrefix($this->items, $value);

        $truncated = array_slice($filtered, 0, self::MAX_VALUES);

        return new DirectCompletionResult($truncated);
    }
}
