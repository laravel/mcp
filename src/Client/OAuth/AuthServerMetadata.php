<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Laravel\Mcp\Client\Exceptions\OAuthException;

class AuthServerMetadata
{
    /**
     * @param  array<int, string>  $codeChallengeMethodsSupported
     * @param  array<int, string>  $tokenEndpointAuthMethodsSupported
     */
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public ?string $registrationEndpoint = null,
        public array $codeChallengeMethodsSupported = [],
        public bool $authorizationResponseIssParameterSupported = false,
        public array $tokenEndpointAuthMethodsSupported = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (empty($data['authorization_endpoint']) || empty($data['token_endpoint'])) {
            throw new OAuthException('Authorization server metadata is missing required endpoints.');
        }

        return new self(
            issuer: (string) ($data['issuer'] ?? ''),
            authorizationEndpoint: (string) $data['authorization_endpoint'],
            tokenEndpoint: (string) $data['token_endpoint'],
            registrationEndpoint: isset($data['registration_endpoint']) ? (string) $data['registration_endpoint'] : null,
            codeChallengeMethodsSupported: array_values(array_map(strval(...), (array) ($data['code_challenge_methods_supported'] ?? []))),
            authorizationResponseIssParameterSupported: (bool) ($data['authorization_response_iss_parameter_supported'] ?? false),
            tokenEndpointAuthMethodsSupported: array_values(array_map(strval(...), (array) ($data['token_endpoint_auth_methods_supported'] ?? []))),
        );
    }
}
