<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | An "*" may be used to allow all domains.
    |
    */

    'redirect_domains' => [
        '*',
        // 'https://example.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session TTL
    |--------------------------------------------------------------------------
    |
    | This value determines how long (in seconds) MCP session data will be
    | cached. Session data includes log level preferences and another
    | per-session state. The default is 86,400 seconds (24 hours).
    |
    */

    'session_ttl' => env('MCP_SESSION_TTL', 86400),

];
