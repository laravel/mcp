<?php

declare(strict_types=1);

namespace Laravel\Mcp\Events;

class SessionInitialized
{
    /**
     * @param  array{name?: string, title?: string, version?: string}|null  $clientInfo
     * @param  array<string, mixed>|null  $clientCapabilities
     *
     * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/lifecycle#initialization
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly ?array $clientInfo,
        public readonly ?string $protocolVersion,
        public readonly ?array $clientCapabilities,
    ) {
        //
    }

    /**
     * Get the client name from clientInfo, if available.
     */
    public function clientName(): ?string
    {
        return $this->clientInfo['name'] ?? null;
    }

    /**
     * Get the client title from clientInfo, if available.
     */
    public function clientTitle(): ?string
    {
        return $this->clientInfo['title'] ?? null;
    }

    /**
     * Get the client version from clientInfo, if available.
     */
    public function clientVersion(): ?string
    {
        return $this->clientInfo['version'] ?? null;
    }
}
