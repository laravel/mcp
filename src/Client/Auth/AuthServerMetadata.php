<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class AuthServerMetadata
{
    /**
     * @param  array<int, string>  $grantTypesSupported
     * @param  array<int, string>  $codeChallengeMethodsSupported
     */
    public function __construct(
        public string $issuer,
        public string $tokenEndpoint,
        public ?string $authorizationEndpoint = null,
        public array $grantTypesSupported = [],
        public array $codeChallengeMethodsSupported = [],
        public ?string $registrationEndpoint = null,
    ) {}

    public function supportsPkceS256(): bool
    {
        return in_array('S256', $this->codeChallengeMethodsSupported, true);
    }

    public function supportsDynamicRegistration(): bool
    {
        return $this->registrationEndpoint !== null && $this->registrationEndpoint !== '';
    }
}
