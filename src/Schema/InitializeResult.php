<?php

declare(strict_types=1);

namespace Laravel\Mcp\Schema;

use Laravel\Mcp\Client\Exceptions\ClientException;
use Laravel\Mcp\Enums\ProtocolVersion;

class InitializeResult
{
    /**
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(
        public string $protocolVersion,
        public array $capabilities,
        public Implementation $serverInfo,
        public ?string $instructions = null,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): self
    {
        $protocolVersion = $payload['protocolVersion'] ?? null;
        $capabilities = $payload['capabilities'] ?? null;
        $serverInfo = $payload['serverInfo'] ?? null;

        if (! is_string($protocolVersion)
            || ! in_array($protocolVersion, ProtocolVersion::supported(), true)
            || ! is_array($capabilities)
            || ! is_array($serverInfo)
            || ! is_string($serverInfo['name'] ?? null)
            || ! is_string($serverInfo['version'] ?? null)) {
            throw new ClientException('Invalid initialize response from server.');
        }

        $instructions = $payload['instructions'] ?? null;

        return new self(
            protocolVersion: $protocolVersion,
            capabilities: $capabilities,
            serverInfo: Implementation::from($serverInfo),
            instructions: is_string($instructions) ? $instructions : null,
        );
    }
}
