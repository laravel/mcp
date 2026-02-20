<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Exceptions;

use Laravel\Mcp\Client\Auth\AuthorizationCodeProvider;

class AuthorizationRequiredException extends ClientException
{
    public function __construct(
        public readonly string $authorizationUrl,
        public readonly string $state,
        public readonly AuthorizationCodeProvider $provider,
    ) {
        parent::__construct('Authorization required. Redirect user to the authorization URL.');
    }
}
