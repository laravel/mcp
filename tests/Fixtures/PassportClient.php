<?php

declare(strict_types=1);

namespace Laravel\Passport;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $keyType = 'string';

    protected $table = 'oauth_clients';

    protected function casts(): array
    {
        return [
            'grant_types' => 'array',
            'redirect_uris' => 'array',
            'scopes' => 'array',
        ];
    }

    public function getConnectionName(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
}
