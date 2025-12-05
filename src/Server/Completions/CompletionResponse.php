<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
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

    /**
     * @param  array<int, string>|string  $values
     */
    public static function from(array|string $values): CompletionResponse
    {
        $values = Arr::wrap($values);

        $hasMore = count($values) > self::MAX_VALUES;

        if ($hasMore) {
            $values = array_slice($values, 0, self::MAX_VALUES);
        }

        return new DirectCompletionResponse($values, $hasMore);
    }

    public static function empty(): CompletionResponse
    {
        return new DirectCompletionResponse([]);
    }

    /**
     * @param  array<int, string>  $items
     */
    public static function fromArray(array $items): CompletionResponse
    {
        return new ArrayCompletionResponse($items);
    }

    /**
     * @param  class-string<UnitEnum>  $enumClass
     */
    public static function fromEnum(string $enumClass): CompletionResponse
    {
        return new EnumCompletionResponse($enumClass);
    }

    public static function fromCallback(callable $callback): CompletionResponse
    {
        return new CallbackCompletionResponse($callback);
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
