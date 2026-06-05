<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

class OAuthConfig
{
    public function __construct(
        public ?string $clientId = null,
        public ?string $clientSecret = null,
        public ?string $scope = null,
        public ?string $redirectUri = null,
    ) {}
}
