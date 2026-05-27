<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class CacheClientRegistrationStore extends EncryptedCacheStore implements ClientRegistrationStore
{
    public function get(string $key): ?ClientRegistration
    {
        $data = $this->readDecrypted($key);

        return $data === null ? null : ClientRegistration::fromArray($data);
    }

    public function put(string $key, ClientRegistration $registration): void
    {
        $this->writeEncrypted($key, $registration->toArray());
    }
}
