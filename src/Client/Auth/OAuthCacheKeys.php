<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class OAuthCacheKeys
{
    protected const PREFIX_TOKENS = 'mcp-auth:';

    protected const PREFIX_REGISTRATION = 'mcp-client:';

    protected const PREFIX_STATE = 'mcp-oauth-state:';

    protected const PREFIX_REFRESH_LOCK = 'mcp-auth-refresh:';

    public static function tokens(string $serverName, ?string $userKey = null): string
    {
        $base = self::PREFIX_TOKENS.$serverName;

        return $userKey === null ? $base : $base.':user:'.$userKey;
    }

    public static function registration(string $serverName): string
    {
        return self::PREFIX_REGISTRATION.$serverName;
    }

    public static function state(string $state): string
    {
        return self::PREFIX_STATE.$state;
    }

    public static function refreshLock(string $tokenKey): string
    {
        return self::PREFIX_REFRESH_LOCK.$tokenKey;
    }
}
