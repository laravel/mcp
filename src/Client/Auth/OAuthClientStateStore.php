<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Illuminate\Contracts\Cache\Repository;
use Laravel\Mcp\Exceptions\OAuthException;
use Throwable;

class OAuthClientStateStore
{
    public function __construct(
        protected Repository $cache,
        protected int $ttlSeconds = 600,
    ) {}

    public function put(string $state, OAuthSession $session): void
    {
        try {
            $this->cache->put(OAuthCacheKeys::state($state), $session->toArray(), $this->ttlSeconds);
        } catch (Throwable $throwable) {
            throw new OAuthException("Failed to persist OAuth state [{$state}]: {$throwable->getMessage()}.", $throwable->getCode(), previous: $throwable);
        }
    }

    public function pull(string $state): ?OAuthSession
    {
        try {
            $payload = $this->cache->pull(OAuthCacheKeys::state($state));
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        return OAuthSession::fromArray($payload);
    }
}
