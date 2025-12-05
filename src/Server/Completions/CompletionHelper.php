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
        if ($prefix === '') {
            return $items;
        }

        $prefixLower = Str::lower($prefix);

        return array_values(array_filter(
            $items,
            fn (string $item) => Str::startsWith(Str::lower($item), $prefixLower)
        ));
    }
}
