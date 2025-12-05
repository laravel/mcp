<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

use Illuminate\Support\Arr;

final class CallbackCompletionResponse extends CompletionResponse
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

        $truncated = array_slice(Arr::wrap($result), 0, self::MAX_VALUES);

        return new DirectCompletionResponse($truncated);
    }
}
