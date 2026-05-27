<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Closure;

class OAuthAuthorizationStrategy implements AuthorizationStrategy
{
    protected ?OAuthHandler $handler = null;

    /**
     * @param  Closure(): OAuthHandler  $handlerResolver
     */
    public function __construct(
        protected Closure $handlerResolver,
    ) {}

    public function bearer(): ?string
    {
        return $this->handler()->bearerToken();
    }

    public function cachedBearer(): ?string
    {
        return $this->handler()->bearerTokenIfCached();
    }

    public function bearerAfterChallenge(WwwAuthenticateChallenge $challenge): ?string
    {
        return $this->handler()->bearerTokenAfterChallenge($challenge);
    }

    private function handler(): OAuthHandler
    {
        return $this->handler ??= ($this->handlerResolver)();
    }
}
