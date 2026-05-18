<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Implementation;
use stdClass;

class Initialize implements Method
{
    public function __construct(
        protected Implementation $clientInfo,
    ) {
        //
    }

    public function method(): string
    {
        return 'initialize';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            'protocolVersion' => ProtocolVersion::LATEST->value,
            'capabilities' => new stdClass,
            'clientInfo' => $this->clientInfo->toArray(),
        ];
    }
}
