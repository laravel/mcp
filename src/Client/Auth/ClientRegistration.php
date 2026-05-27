<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class ClientRegistration
{
    public function __construct(
        public readonly string $clientId,
        public readonly ?string $clientSecret = null,
        public readonly ?int $clientIdIssuedAt = null,
        public readonly ?int $clientSecretExpiresAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientId: (string) ($data['client_id'] ?? ''),
            clientSecret: isset($data['client_secret']) && $data['client_secret'] !== '' ? (string) $data['client_secret'] : null,
            clientIdIssuedAt: isset($data['client_id_issued_at']) ? (int) $data['client_id_issued_at'] : null,
            clientSecretExpiresAt: isset($data['client_secret_expires_at']) ? (int) $data['client_secret_expires_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'client_id_issued_at' => $this->clientIdIssuedAt,
            'client_secret_expires_at' => $this->clientSecretExpiresAt,
        ];
    }

    public function isSecretExpired(): bool
    {
        if ($this->clientSecretExpiresAt === null || $this->clientSecretExpiresAt === 0) {
            return false;
        }

        return $this->clientSecretExpiresAt <= time();
    }
}
