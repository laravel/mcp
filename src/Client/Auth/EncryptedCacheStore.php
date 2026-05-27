<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Throwable;

abstract class EncryptedCacheStore
{
    public function __construct(
        protected Repository $cache,
        protected StringEncrypter $crypt,
    ) {}

    public function forget(string $key): void
    {
        try {
            $this->cache->forget($key);
        } catch (Throwable) {
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    protected function readDecrypted(string $key): ?array
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
     * @param  array<string, mixed>  $data
     */
    protected function writeEncrypted(string $key, array $data, ?int $ttlSeconds = null): void
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
}
