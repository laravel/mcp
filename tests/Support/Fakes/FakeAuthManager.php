<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

class FakeAuthManager
{
    public function __construct(protected mixed $user)
    {
        //
    }

    public function userResolver(): callable
    {
        return fn (?string $guard = null): mixed => $this->user;
    }
}
