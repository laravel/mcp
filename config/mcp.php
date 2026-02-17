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
    | Allow Localhost Dynamic Ports
    |--------------------------------------------------------------------------
    |
    | When enabled, any HTTP redirect URI targeting localhost (localhost,
    | 127.0.0.1, or [::1]) will be permitted regardless of the port
    | number. This is useful for CLI-based MCP clients that bind to
    | dynamically allocated ports on the loopback interface.
    |
    */

    'allow_localhost_dynamic_port' => true,

];
