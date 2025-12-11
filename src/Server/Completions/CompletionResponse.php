<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use UnitEnum;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class CompletionResponse implements Arrayable
{
    protected const MAX_VALUES = 100;

    /**
     * @param  array<int, string>  $values
     */
    public function __construct(
        protected array $values,
        protected bool $hasMore = false,
    ) {
        if (count($values) > self::MAX_VALUES) {
            throw new InvalidArgumentException(
                sprintf('Completion values cannot exceed %d items (received %d)', self::MAX_VALUES, count($values))
            );
        }
    }

    public static function empty(): CompletionResponse
    {
        return new DirectCompletionResponse([]);
    }

    /**
     * @param  array<int, string>|class-string<UnitEnum>|callable  $items
     */
    public static function match(array|string|callable $items): CompletionResponse
    {
        if (is_callable($items)) {
            return new CallbackCompletionResponse($items);
        }

        if (is_string($items)) {
            return new EnumCompletionResponse($items);
        }

        return new ArrayCompletionResponse($items);
    }

    abstract public function resolve(string $value): CompletionResponse;

    /**
     * @return array<int, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    /**
     * @return array{values: array<int, string>, total: int, hasMore: bool}
     */
    public function toArray(): array
    {
        return [
            'values' => $this->values,
            'total' => count($this->values),
            'hasMore' => $this->hasMore,
        ];
    }
}
