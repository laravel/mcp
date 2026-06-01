<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Illuminate\Support\Arr;

class TokenSet
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresAt,
        public ?string $scope,
        public ?int $refreshExpiresAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: (string) Arr::get($data, 'access_token', ''),
            refreshToken: filled($data['refresh_token'] ?? null) ? (string) $data['refresh_token'] : null,
            expiresAt: (int) Arr::get($data, 'expires_at', 0),
            scope: filled($data['scope'] ?? null) ? (string) $data['scope'] : null,
            refreshExpiresAt: filled($data['refresh_expires_at'] ?? null) ? (int) $data['refresh_expires_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt,
            'scope' => $this->scope,
            'refresh_expires_at' => $this->refreshExpiresAt,
        ];
    }

    public function isExpired(int $skewSeconds = 30): bool
    {
        if ($this->expiresAt === 0) {
            return false;
        }

        return $this->expiresAt - $skewSeconds <= time();
    }
}
