<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\InitializeResult;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Schema\Implementation;

/**
 * @implements Method<InitializeResult>
 */
class Initialize implements Method
{
    public function __construct(protected Implementation $clientInfo)
    {
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
            'capabilities' => (object) [],
            'clientInfo' => $this->clientInfo->toArray(),
        ];
    }

    public function handle(Protocol $protocol): InitializeResult
    {
        return InitializeResult::from($protocol->dispatch($this));
    }
}
