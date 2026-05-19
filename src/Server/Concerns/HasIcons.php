<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use Laravel\Mcp\Schema\Icon;
use Laravel\Mcp\Server\Attributes\Icon as IconAttribute;

trait HasIcons
{
    use ReadsAttributes;

    /**
     * @return list<Icon>
     */
    public function resolvedIcons(): array
    {
        $attributeIcons = array_map(
            fn (IconAttribute $icon): Icon => $icon->toIcon(),
            $this->resolveAttributes(IconAttribute::class),
        );

        return [...$attributeIcons, ...$this->icons()];
    }
}
