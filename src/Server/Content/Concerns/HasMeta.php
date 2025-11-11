<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Content\Concerns;

trait HasMeta
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $meta = null;

    /**
     * @param  array<string, mixed>  $baseArray
     * @return array<string, mixed>
     */
    protected function withMeta(array $baseArray): array
    {
        return ($meta = $this->meta)
            ? [...$baseArray, '_meta' => $meta]
            : $baseArray;
    }
}
