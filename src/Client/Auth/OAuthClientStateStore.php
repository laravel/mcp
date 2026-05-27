<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Illuminate\Contracts\Cache\Repository;
use Throwable;

class OAuthClientStateStore
{
    protected const KEY_PREFIX = 'mcp-oauth-state:';

    public function __construct(
        protected Repository $cache,
        protected int $ttlSeconds = 600,
    ) {}

    /**
     * @param  array{server: string, user_key: ?string, pkce_verifier: string, intended_url: ?string, scope: ?string}  $payload
     */
    public function put(string $state, array $payload): void
    {
        try {
            $this->cache->put(self::KEY_PREFIX.$state, $payload, $this->ttlSeconds);
        } catch (Throwable) {
        }
    }

    /**
     * @return ?array{server: string, user_key: ?string, pkce_verifier: string, intended_url: ?string, scope: ?string}
     */
    public function pull(string $state): ?array
    {
        try {
            $payload = $this->cache->pull(self::KEY_PREFIX.$state);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['pkce_verifier'], $payload['server'])) {
            return null;
        }

        /** @var array{server: string, user_key: ?string, pkce_verifier: string, intended_url: ?string, scope: ?string} $payload */
        return $payload;
    }
}
