<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

class ClientRegistration
{
    public function __construct(
        public string $clientId,
        public ?string $clientSecret = null,
    ) {}
}
