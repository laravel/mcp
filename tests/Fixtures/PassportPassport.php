<?php

declare(strict_types=1);

namespace Laravel\Passport;

class Passport
{
    public static $scopes = [];

    public static function tokensCan($scopes)
    {
        self::$scopes = $scopes;
    }
}
