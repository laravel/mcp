<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Passport\Client;

class CustomPassportClient extends Client
{
    protected $connection = 'clients';

    protected $table = 'registered_clients';

    protected $guarded = ['*'];

    public function setLogoUriAttribute(?string $value): void
    {
        $this->attributes['logo_uri'] = $value === null ? null : strtolower($value);
    }
}
