<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport\Eras;

use Illuminate\Http\Client\Response as ClientResponse;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\RequestHeader;

class LegacyEra implements Era
{
    protected ?string $sessionId = null;

    protected bool $initialized = false;

    public function __construct(protected ProtocolVersion $version = ProtocolVersion::V2025_11_25)
    {
        //
    }

    public function speaking(ProtocolVersion $version): Era
    {
        if ($version->usesDiscovery()) {
            return new ModernEra($version);
        }

        $this->version = $version;

        return $this;
    }

    public function headers(array $body): array
    {
        $headers = [];

        if ($this->sessionId !== null) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        if ($this->initialized && ($body['method'] ?? null) !== 'initialize') {
            $headers[RequestHeader::PROTOCOL_VERSION->value] = $this->version->value;
        }

        return $headers;
    }

    public function inspect(ClientResponse $response): void
    {
        $sessionId = $response->header('MCP-Session-Id');

        if ($sessionId !== '') {
            $this->sessionId = $sessionId;
        }

        if ($response->successful()) {
            $this->initialized = true;
        }
    }

    public function hasSession(): bool
    {
        return $this->sessionId !== null;
    }
}
