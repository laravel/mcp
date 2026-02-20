<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

interface AuthProvider
{
    public function token(): ?string;

    public function handleUnauthorized(string $wwwAuthenticate): void;
}
