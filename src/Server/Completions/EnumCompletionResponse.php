<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

use BackedEnum;
use InvalidArgumentException;
use UnitEnum;

class EnumCompletionResponse extends CompletionResponse
{
    /**
     * @param  class-string<UnitEnum>  $enumClass
     */
    public function __construct(private string $enumClass)
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException("Class [{$enumClass}] is not an enum.");
        }

        parent::__construct([]);
    }

    public function resolve(string $value): DirectCompletionResponse
    {
        $enumValues = array_map(
            fn (UnitEnum $case): string => $case instanceof BackedEnum ? (string) $case->value : $case->name,
            $this->enumClass::cases()
        );

        $filtered = CompletionHelper::filterByPrefix($enumValues, $value);

        $hasMore = count($filtered) > self::MAX_VALUES;

        $truncated = array_slice($filtered, 0, self::MAX_VALUES);

        return new DirectCompletionResponse($truncated, $hasMore);
    }
}
