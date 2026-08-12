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
        $meta = Arr::wrap(Arr::get($payload, '_meta'));

        if (! is_array($supportedVersions) || ! is_array($capabilities)) {
            throw new ClientException('Invalid discover response from server.');
        }

        return new self(
            supportedVersions: array_values(array_filter($supportedVersions, is_string(...))),
            capabilities: $capabilities,
            serverInfo: Implementation::tryFrom(Arr::get($meta, MetaKey::SERVER_INFO->value)),
            instructions: is_string($instructions) ? $instructions : null,
        );
    }
}
