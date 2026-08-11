<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport\Eras;

use Illuminate\Http\Client\Response as ClientResponse;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\RequestHeader;
use Laravel\Mcp\Transport\JsonRpcRequest;

class ModernEra implements Era
{
    public function __construct(protected ProtocolVersion $version)
    {
        //
    }

    public function speaking(ProtocolVersion $version): Era
    {
        if (! $version->usesDiscovery()) {
            return new LegacyEra($version);
        }

        $this->version = $version;

        return $this;
    }

    public function headers(array $body): array
    {
        return [
            RequestHeader::PROTOCOL_VERSION->value => $this->version->value,
            ...$this->mirroredHeaders($body),
        ];
    }

    public function inspect(ClientResponse $response): void
    {
        //
    }

    public function hasSession(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, string>
     */
    protected function mirroredHeaders(array $body): array
    {
        if (! isset($body['id']) || ! is_string($body['method'] ?? null)) {
            return [];
        }

        return (new JsonRpcRequest(
            id: is_int($body['id']) || is_string($body['id']) ? $body['id'] : 0,
            method: $body['method'],
            params: is_array($body['params'] ?? null) ? $body['params'] : [],
        ))->mirroredHeaders();
    }
}
