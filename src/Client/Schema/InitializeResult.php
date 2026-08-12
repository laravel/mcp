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
        $serverInfo = Implementation::tryFrom(Arr::get($payload, 'serverInfo'));
        $instructions = Arr::get($payload, 'instructions');

        $version = is_string($protocolVersion) ? ProtocolVersion::tryFrom($protocolVersion) : null;

        if (! $version instanceof ProtocolVersion || ! in_array($version->value, ProtocolVersion::initializeSupported(), true)) {
            throw new ClientException(sprintf(
                'The server chose protocol version [%s]. This client supports [%s].',
                is_string($protocolVersion) ? $protocolVersion : 'none',
                implode(', ', ProtocolVersion::initializeSupported()),
            ));
        }

        if (! is_array($capabilities) || ! $serverInfo instanceof Implementation) {
            throw new ClientException('Invalid initialize response from server.');
        }

        return new self(
            protocolVersion: $protocolVersion,
            capabilities: $capabilities,
            serverInfo: $serverInfo,
            instructions: is_string($instructions) ? $instructions : null,
        );
    }
}
