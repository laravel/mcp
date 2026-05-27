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
    ) {}
}
