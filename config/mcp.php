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
        // 'http://localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Native desktop OAuth clients (Cursor, VS Code, Claude Desktop, etc.) use
    | private-use URI schemes (RFC 8252) for redirect callbacks instead of
    | standard http(s) URLs. List the custom schemes you wish to permit.
    |
    */

    'allowed_custom_schemes' => [
        // 'cursor',
        // 'vscode',
        // 'claude',
    ],

];
