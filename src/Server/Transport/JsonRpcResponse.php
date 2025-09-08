<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements  Arrayable<string, mixed>
 */
abstract class JsonRpcResponse implements Arrayable
{
    abstract public function toArray(): array;

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '';
    }
}
