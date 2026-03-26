<?php

declare(strict_types=1);

namespace Laravel\Passport;

class ClientRepository
{
    public function createAuthorizationCodeGrantClient(string $name, array $redirectUris, bool $confidential = true, $user = null, bool $enableDeviceFlow = false)
    {
        return (object) [
            'id' => 'test-client-id',
            'grant_types' => ['authorization_code'],
            'redirect_uris' => $redirectUris,
        ];
    }
}
