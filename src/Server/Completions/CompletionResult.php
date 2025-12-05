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
abstract class CompletionResult implements Arrayable
{
    protected const MAX_VALUES = 100;

    /**
     * @param  array<int, string>  $values
     */
    public function __construct(
        protected array $values,
        protected ?int $total = null,
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
    public static function make(array|string $values): CompletionResult
    {
        $values = Arr::wrap($values);

        if (count($values) > self::MAX_VALUES) {
            $values = array_slice($values, 0, self::MAX_VALUES);
        }

        return new DirectCompletionResult($values);
    }

    public static function empty(): CompletionResult
    {
        return new DirectCompletionResult([]);
    }

    /**
     * @param  array<int, string>  $items
     */
    public static function usingList(array $items): CompletionResult
    {
        return new ListCompletionResult($items);
    }

    /**
     * @param  class-string<UnitEnum>  $enumClass
     */
    public static function usingEnum(string $enumClass): CompletionResult
    {
        return new EnumCompletionResult($enumClass);
    }

    public static function using(callable $callback): CompletionResult
    {
        return new CallbackCompletionResult($callback);
    }

    abstract public function resolve(string $value): CompletionResult;

    /**
     * @return array<int, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function total(): ?int
    {
        return $this->total;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    /**
     * @return array{values: array<int, string>, total?: int, hasMore: bool}
     */
    public function toArray(): array
    {
        $result = [
            'values' => $this->values,
        ];

        if (! is_null($this->total)) {
            $result['total'] = $this->total;
        }

        $result['hasMore'] = $this->hasMore;

        return $result;
    }
}
