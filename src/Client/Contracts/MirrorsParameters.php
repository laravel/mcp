<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Contracts;

interface MirrorsParameters
{
    /**
     * @return array<string, string>
     */
    public function requestHeaders(): array;
}
