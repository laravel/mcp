<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class AuthorizationRedirect
{
    public function __construct(
        public readonly string $url,
        public readonly string $state,
    ) {}
}
