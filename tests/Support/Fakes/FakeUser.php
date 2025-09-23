<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Illuminate\Contracts\Auth\Authenticatable;

class FakeUser implements Authenticatable
{
    public function __construct(
        /** @var array<int,string> */
        protected array $abilities = [],
        /** @var array<int,string> */
        protected array $scopes = []
    ) {}

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return 1;
    }

    public function getAuthPassword()
    {
        return 'secret';
    }

    public function getAuthPasswordName()
    {
        return 'password';
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function can($ability, $arguments = []): bool
    {
        return in_array($ability, $this->abilities, true);
    }

    public function tokenCan(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
