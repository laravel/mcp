<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Schema;

use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Schema\Implementation;

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
        $protocolVersion = Arr::get($payload, 'protocolVersion');
        $capabilities = Arr::get($payload, 'capabilities');
        /** @var array{name: string, version: string, title?: string, description?: string, icons?: array<int, array{src: string, mimeType?: string, sizes?: array<string>, theme?: string}>, websiteUrl?: string} $serverInfo */
        $serverInfo = Arr::get($payload, 'serverInfo');
        $serverName = Arr::get($serverInfo, 'name');
        $serverVersion = Arr::get($serverInfo, 'version');
        $instructions = Arr::get($payload, 'instructions');

        if (! is_string($protocolVersion)
            || ! in_array($protocolVersion, ProtocolVersion::supported(), true)
            || ! is_array($capabilities)
            || ! is_array($serverInfo)
            || ! is_string($serverName)
            || ! is_string($serverVersion)) {
            throw new ClientException('Invalid initialize response from server.');
        }

        return new self(
            protocolVersion: $protocolVersion,
            capabilities: $capabilities,
            serverInfo: Implementation::from($serverInfo),
            instructions: is_string($instructions) ? $instructions : null,
        );
    }
}
