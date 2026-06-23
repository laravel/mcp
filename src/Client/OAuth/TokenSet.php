<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

class TokenSet
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?int $expiresAt = null,
        public string $tokenType = 'Bearer',
        public ?string $scope = null,
        public ?string $clientId = null,
        public ?string $clientSecret = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(array $data): self
    {
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;

        return new self(
            accessToken: (string) ($data['access_token'] ?? ''),
            refreshToken: isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            expiresAt: $expiresIn !== null ? time() + $expiresIn : null,
            tokenType: (string) ($data['token_type'] ?? 'Bearer'),
            scope: isset($data['scope']) ? (string) $data['scope'] : null,
        );
    }
}
