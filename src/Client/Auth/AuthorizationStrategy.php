<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

interface AuthorizationStrategy
{
    public function bearer(): ?string;

    public function cachedBearer(): ?string;

    public function bearerAfterChallenge(WwwAuthenticateChallenge $challenge): ?string;
}
