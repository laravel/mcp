<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

class PkceUnsupportedException extends OAuthException
{
    public function __construct(
        public readonly string $issuer,
        string $message = '',
    ) {
        parent::__construct($message === '' ? "Authorization server [{$issuer}] does not advertise PKCE S256 support; refusing to start authorization_code flow." : $message);
    }
}
