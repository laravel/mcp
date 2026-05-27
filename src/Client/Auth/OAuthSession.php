<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class OAuthSession
{
    public function __construct(
        public string $serverName,
        public string $pkceVerifier,
        public ?string $userKey = null,
        public ?string $intendedUrl = null,
        public ?string $scope = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'server_name' => $this->serverName,
            'pkce_verifier' => $this->pkceVerifier,
            'user_key' => $this->userKey,
            'intended_url' => $this->intendedUrl,
            'scope' => $this->scope,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $server = $data['server_name'] ?? null;
        $verifier = $data['pkce_verifier'] ?? null;

        if (! is_string($server) || $server === '' || ! is_string($verifier) || $verifier === '') {
            return null;
        }

        return new self(
            serverName: $server,
            pkceVerifier: $verifier,
            userKey: isset($data['user_key']) ? (string) $data['user_key'] : null,
            intendedUrl: isset($data['intended_url']) ? (string) $data['intended_url'] : null,
            scope: isset($data['scope']) ? (string) $data['scope'] : null,
        );
    }
}
