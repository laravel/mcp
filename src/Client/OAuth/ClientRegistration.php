<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Laravel\Mcp\Client\OAuth\Enums\TokenEndpointAuthMethod;
use SensitiveParameter;

class ClientRegistration
{
    public function __construct(
        public string $clientId,
        #[SensitiveParameter]
        public ?string $clientSecret = null,
        public ?TokenEndpointAuthMethod $tokenEndpointAuthMethod = null,
    ) {}
}
