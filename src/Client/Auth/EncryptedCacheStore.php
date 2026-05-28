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

class EncryptedCacheStore
{
    public function __construct(
        protected Repository $cache,
        protected StringEncrypter $crypt,
        protected int $lockHoldSeconds = 10,
        protected int $lockWaitSeconds = 5,
    ) {}

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $key): ?array
    {
        try {
            $payload = $this->cache->get($key);

            if (! is_string($payload) || $payload === '') {
                return null;
            }

            $data = json_decode($this->crypt->decryptString($payload), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function pull(string $key): ?array
    {
        $data = $this->get($key);

        $this->forget($key);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $key, array $data, ?int $ttlSeconds = null): void
    {
        try {
            $payload = $this->crypt->encryptString(json_encode($data, JSON_THROW_ON_ERROR));

            if ($ttlSeconds === null) {
                $this->cache->forever($key, $payload);

                return;
            }

            $this->cache->put($key, $payload, $ttlSeconds);
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

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function lock(string $key, Closure $work): mixed
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $work();
        }

        $lock = $store->lock($key, $this->lockHoldSeconds);

        try {
            return $lock->block($this->lockWaitSeconds, $work);
        } catch (LockTimeoutException $lockTimeoutException) {
            throw new OAuthException("Timed out waiting for MCP token refresh lock [{$key}].", $lockTimeoutException->getCode(), previous: $lockTimeoutException);
        }
    }
}
