<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts\Transport;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @extends Arrayable<string, mixed>
 */
interface JsonRpcResponse extends Arrayable
{
    //
}
