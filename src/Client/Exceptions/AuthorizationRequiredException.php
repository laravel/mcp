<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Exceptions;

use Laravel\Mcp\Client\OAuth\WwwAuthenticateChallenge;

class AuthorizationRequiredException extends OAuthException
{
    public function __construct(
        string $message = 'Authorization is required to access the MCP server.',
        public ?WwwAuthenticateChallenge $challenge = null,
    ) {
        parent::__construct($message);
    }

    public function resourceMetadataUrl(): ?string
    {
        return $this->challenge?->resourceMetadataUrl;
    }

    public function scope(): ?string
    {
        return $this->challenge?->scope;
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'resource_metadata' => $this->resourceMetadataUrl(),
            'scope' => $this->scope(),
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
