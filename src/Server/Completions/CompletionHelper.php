<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Completions;

use Illuminate\Support\Str;

class CompletionHelper
{
    /**
     * @param  array<string>  $items
     * @return array<string>
     */
    public static function filterByPrefix(array $items, string $prefix): array
    {
        return self::filterByStringComparison(
            $items,
            $prefix,
            fn (string $item, string $needle) => Str::startsWith($item, $needle)
        );
    }

    /**
     * @param  array<string>  $items
     * @return array<string>
     */
    private static function filterByStringComparison(
        array $items,
        string $needle,
        callable $comparator
    ): array {
        if ($needle === '') {
            return $items;
        }

        $needleLower = Str::lower($needle);

        return array_values(array_filter(
            $items,
            fn (string $item) => $comparator(Str::lower($item), $needleLower)
        ));
    }
}
