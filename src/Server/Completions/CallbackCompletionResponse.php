<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

use Illuminate\Support\Arr;

class CallbackCompletionResponse extends CompletionResponse
{
    /**
     * @param  callable(string): (CompletionResponse|array<int, string>|string)  $callback
     */
    public function __construct(private $callback)
    {
        parent::__construct([]);
    }

    public function resolve(string $value): CompletionResponse
    {
        $result = ($this->callback)($value);

        if ($result instanceof CompletionResponse) {
            return $result;
        }

        $items = Arr::wrap($result);

        $hasMore = count($items) > self::MAX_VALUES;

        $truncated = array_slice($items, 0, self::MAX_VALUES);

        return new DirectCompletionResponse($truncated, $hasMore);
    }
}
