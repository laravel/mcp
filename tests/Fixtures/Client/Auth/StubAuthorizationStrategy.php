<?php

declare(strict_types=1);

namespace Tests\Fixtures\Client\Auth;

use Closure;
use Laravel\Mcp\Client\Auth\AuthorizationStrategy;
use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;

class StubAuthorizationStrategy implements AuthorizationStrategy
{
    /**
     * @param  ?Closure(): ?string  $bearer
     * @param  ?Closure(): ?string  $cachedBearer
     * @param  ?Closure(WwwAuthenticateChallenge): ?string  $bearerAfterChallenge
     */
    public function __construct(
        protected ?Closure $bearer = null,
        protected ?Closure $cachedBearer = null,
        protected ?Closure $bearerAfterChallenge = null,
    ) {}

    public function bearer(): ?string
    {
        return $this->bearer instanceof Closure ? ($this->bearer)() : null;
    }

    public function cachedBearer(): ?string
    {
        return $this->cachedBearer instanceof Closure ? ($this->cachedBearer)() : null;
    }

    public function bearerAfterChallenge(WwwAuthenticateChallenge $challenge): ?string
    {
        return $this->bearerAfterChallenge instanceof Closure ? ($this->bearerAfterChallenge)($challenge) : null;
    }
}
