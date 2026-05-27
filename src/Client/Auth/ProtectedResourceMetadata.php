<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

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
        return $this->authorizationServers[0];
    }
}
