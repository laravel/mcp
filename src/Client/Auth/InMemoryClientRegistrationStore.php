<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class InMemoryClientRegistrationStore implements ClientRegistrationStore
{
    /** @var array<string, ClientRegistration> */
    protected array $registrations = [];

    public function get(string $key): ?ClientRegistration
    {
        return $this->registrations[$key] ?? null;
    }

    public function put(string $key, ClientRegistration $registration): void
    {
        $this->registrations[$key] = $registration;
    }

    public function forget(string $key): void
    {
        unset($this->registrations[$key]);
    }
}
