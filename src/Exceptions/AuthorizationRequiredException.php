<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

class AuthorizationRequiredException extends OAuthException
{
    public function __construct(
        public readonly string $serverName,
        public readonly ?string $resourceMetadataUrl = null,
        string $message = '',
    ) {
        parent::__construct($message === '' ? "MCP client [{$serverName}] requires interactive authorization." : $message);
    }
}
