<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

use Illuminate\Support\Arr;

final class CallbackCompletionResult extends CompletionResult
{
    /**
     * @param  callable(string): (CompletionResult|array<int, string>|string)  $callback
     */
    public function __construct(private $callback)
    {
        parent::__construct([]);
    }

    public function resolve(string $value): CompletionResult
    {
        $result = ($this->callback)($value);

        if ($result instanceof CompletionResult) {
            return $result;
        }

        $truncated = array_slice(Arr::wrap($result), 0, self::MAX_VALUES);

        return new DirectCompletionResult($truncated);
    }
}
