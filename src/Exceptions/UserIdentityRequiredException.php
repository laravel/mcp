<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

class UserIdentityRequiredException extends OAuthException
{
    public function __construct(
        public readonly string $serverName,
        string $message = '',
    ) {
        parent::__construct($message === '' ? "MCP client [{$serverName}] is scoped with forUser() but no user identity could be resolved." : $message);
    }
}
