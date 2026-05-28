<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Laravel\Mcp\Exceptions\OAuthException;

class CacheTokenStore extends EncryptedCacheStore implements TokenStore
{
    protected const DEFAULT_TTL_SECONDS = 3600;

    protected const MINIMUM_TTL_SECONDS = 60;

    protected const CLOCK_SKEW_SECONDS = 30;

    protected const REFRESH_TOKEN_TTL_SECONDS = 2592000;

    public function __construct(
        Repository $cache,
        StringEncrypter $crypt,
        protected int $lockHoldSeconds = 10,
        protected int $lockWaitSeconds = 5,
        protected int $refreshTtlSeconds = self::REFRESH_TOKEN_TTL_SECONDS,
    ) {
        parent::__construct($cache, $crypt);
    }

    public function get(string $key): ?TokenSet
    {
        $data = $this->readDecrypted($key);

        return $data === null ? null : TokenSet::fromArray($data);
    }

    public function put(string $key, TokenSet $set): void
    {
        $this->writeEncrypted($key, $set->toArray(), $this->ttlFor($set));
    }

    public function lock(string $key, Closure $work): mixed
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $work();
        }

        $lock = $store->lock(OAuthCacheKeys::refreshLock($key), $this->lockHoldSeconds);

        try {
            return $lock->block($this->lockWaitSeconds, $work);
        } catch (LockTimeoutException $lockTimeoutException) {
            throw new OAuthException("Timed out waiting for MCP token refresh lock [{$key}].", $lockTimeoutException->getCode(), previous: $lockTimeoutException);
        }
    }

    protected function ttlFor(TokenSet $set): int
    {
        if ($set->refreshToken !== null) {
            return $this->refreshTtlSeconds;
        }

        if ($set->expiresAt === 0) {
            return self::DEFAULT_TTL_SECONDS;
        }

        $ttl = $set->expiresAt - time() - self::CLOCK_SKEW_SECONDS;

        return max(self::MINIMUM_TTL_SECONDS, $ttl);
    }
}
