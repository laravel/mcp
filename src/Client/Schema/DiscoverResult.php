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
        $meta = Arr::get($payload, '_meta');
        $serverInfo = is_array($meta) ? ($meta[MetaKey::SERVER_INFO->value] ?? null) : null;

        if (! is_array($supportedVersions) || ! is_array($capabilities)) {
            throw new ClientException('Invalid discover response from server.');
        }

        return new self(
            supportedVersions: array_values(array_filter($supportedVersions, is_string(...))),
            capabilities: $capabilities,
            serverInfo: self::serverInfo($serverInfo),
            instructions: is_string($instructions) ? $instructions : null,
        );
    }

    protected static function serverInfo(mixed $serverInfo): ?Implementation
    {
        if (! is_array($serverInfo) || ! is_string($serverInfo['name'] ?? null) || ! is_string($serverInfo['version'] ?? null)) {
            return null;
        }

        /** @var array{name: string, version: string, title?: string, description?: string, icons?: array<int, array{src: string, mimeType?: string, sizes?: array<string>, theme?: string}>, websiteUrl?: string} $serverInfo */
        return Implementation::from($serverInfo);
    }
}
