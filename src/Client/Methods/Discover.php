<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Methods;

use Laravel\Mcp\Client\Contracts\Method;
use Laravel\Mcp\Client\Protocol;
use Laravel\Mcp\Client\Schema\DiscoverResult;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Schema\Implementation;

/**
 * @implements Method<DiscoverResult>
 */
class Discover implements Method
{
    public function __construct(
        protected Implementation $clientInfo,
        protected string $protocolVersion,
    ) {
        //
    }

    public function method(): string
    {
        return 'server/discover';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            '_meta' => [
                MetaKey::PROTOCOL_VERSION->value => $this->protocolVersion,
                MetaKey::CLIENT_INFO->value => $this->clientInfo->toArray(),
                MetaKey::CLIENT_CAPABILITIES->value => (object) [],
            ],
        ];
    }

    public function handle(Protocol $protocol): DiscoverResult
    {
        return DiscoverResult::from($protocol->dispatch($this));
    }
}
