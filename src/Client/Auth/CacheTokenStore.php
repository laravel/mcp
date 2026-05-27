<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Laravel\Mcp\Exceptions\OAuthException;
use Throwable;

class CacheTokenStore implements TokenStore
{
    protected const DEFAULT_TTL_SECONDS = 3600;

    protected const MINIMUM_TTL_SECONDS = 60;

    protected const CLOCK_SKEW_SECONDS = 30;

    public function __construct(
        protected Repository $cache,
        protected StringEncrypter $crypt,
        protected int $lockHoldSeconds = 10,
        protected int $lockWaitSeconds = 5,
    ) {}

    public function get(string $key): ?TokenSet
    {
        try {
            $payload = $this->cache->get($key);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        try {
            $decrypted = $this->crypt->decryptString($payload);
            $data = json_decode($decrypted, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        return TokenSet::fromArray($data);
    }

    public function put(string $key, TokenSet $set): void
    {
        $payload = $this->crypt->encryptString(json_encode($set->toArray(), JSON_THROW_ON_ERROR));

        try {
            $this->cache->put($key, $payload, $this->ttlFor($set));
        } catch (Throwable) {
        }
    }

    public function forget(string $key): void
    {
        try {
            $this->cache->forget($key);
        } catch (Throwable) {
        }
    }

    public function lock(string $key, Closure $work): mixed
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $work();
        }

        $lock = $store->lock("mcp-auth-refresh:{$key}", $this->lockHoldSeconds);

        try {
            return $lock->block($this->lockWaitSeconds, $work);
        } catch (LockTimeoutException $lockTimeoutException) {
            throw new OAuthException("Timed out waiting for MCP token refresh lock [{$key}].", $lockTimeoutException->getCode(), previous: $lockTimeoutException);
        }
    }

    protected function ttlFor(TokenSet $set): int
    {
        if ($set->expiresAt === 0) {
            return self::DEFAULT_TTL_SECONDS;
        }

        $ttl = $set->expiresAt - time() - self::CLOCK_SKEW_SECONDS;

        return max(self::MINIMUM_TTL_SECONDS, $ttl);
    }
}
