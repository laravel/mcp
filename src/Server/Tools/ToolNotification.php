<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class ToolNotification implements Arrayable
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(protected string $method, protected array $params)
    {
        //
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function toArray(): array
    {
        return $this->params;
    }
}
