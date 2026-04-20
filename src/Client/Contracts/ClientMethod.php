<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

use Laravel\Mcp\Client\ClientContext;

interface ClientMethod
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function handle(ClientContext $context, array $params = []): array;
}
