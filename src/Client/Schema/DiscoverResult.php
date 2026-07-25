<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Schema;

use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Schema\Implementation;

class DiscoverResult
{
    /**
     * @param  array<int, string>  $supportedVersions
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(
        public array $supportedVersions,
        public array $capabilities,
        public ?Implementation $serverInfo = null,
        public ?string $instructions = null,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): self
    {
        $supportedVersions = Arr::get($payload, 'supportedVersions');
        $capabilities = Arr::get($payload, 'capabilities');
        $instructions = Arr::get($payload, 'instructions');

        if (! is_array($supportedVersions) || $supportedVersions === [] || $supportedVersions !== array_filter($supportedVersions, is_string(...))) {
            throw new ClientException('Invalid discover response from server.');
        }

        /** @var array{name?: mixed, version?: mixed}|null $serverInfo */
        $serverInfo = $payload['_meta'][MetaKey::SERVER_INFO->value] ?? null;

        $hasServerInfo = is_array($serverInfo)
            && is_string($serverInfo['name'] ?? null)
            && is_string($serverInfo['version'] ?? null);

        return new self(
            supportedVersions: array_values($supportedVersions),
            capabilities: is_array($capabilities) ? $capabilities : [],
            // @phpstan-ignore-next-line
            serverInfo: $hasServerInfo ? Implementation::from($serverInfo) : null,
            instructions: is_string($instructions) ? $instructions : null,
        );
    }

    /**
     * Represent this discovery as the initialize result shape the client API exposes.
     */
    public function toInitializeResult(string $protocolVersion): InitializeResult
    {
        return new InitializeResult(
            protocolVersion: $protocolVersion,
            capabilities: $this->capabilities,
            serverInfo: $this->serverInfo ?? new Implementation(name: 'unknown', version: 'unknown'),
            instructions: $this->instructions,
        );
    }
}
