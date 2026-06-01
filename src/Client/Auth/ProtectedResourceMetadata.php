<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Laravel\Mcp\Exceptions\OAuthException;

class ProtectedResourceMetadata
{
    /**
     * @param  array<int, string>  $authorizationServers
     * @param  array<int, string>  $scopesSupported
     */
    public function __construct(
        public string $resource,
        public array $authorizationServers,
        public array $scopesSupported = [],
    ) {}

    public function primaryAuthorizationServer(): string
    {
        if ($this->authorizationServers === []) {
            throw new OAuthException('Protected resource metadata lists no authorization servers.');
        }

        return $this->authorizationServers[0];
    }
}
