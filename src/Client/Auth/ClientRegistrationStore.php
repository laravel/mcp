<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

interface ClientRegistrationStore
{
    public function get(string $key): ?ClientRegistration;

    public function put(string $key, ClientRegistration $registration): void;

    public function forget(string $key): void;
}
