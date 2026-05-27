<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Throwable;

class CacheClientRegistrationStore implements ClientRegistrationStore
{
    public function __construct(
        protected Repository $cache,
        protected StringEncrypter $crypt,
    ) {}

    public function get(string $key): ?ClientRegistration
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

        return ClientRegistration::fromArray($data);
    }

    public function put(string $key, ClientRegistration $registration): void
    {
        $payload = $this->crypt->encryptString(json_encode($registration->toArray(), JSON_THROW_ON_ERROR));

        try {
            $this->cache->forever($key, $payload);
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
}
