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
    | Native desktop OAuth clients like Cursor and VS Code use private-use URI
    | schemes (RFC 8252) for redirect callbacks instead of standard schemes
    | like HTTPS. Here, you may list which custom schemes you will allow.
    |
    */

    'custom_schemes' => [
        // 'claude',
        // 'cursor',
        // 'vscode',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | The OAuth authorization server issuer identifier (RFC 8414). This URL is
    | used as the `authorization_servers` entry in the Protected Resource
    | Metadata document (RFC 9728) and as the `issuer` claim in the
    | Authorization Server Metadata document.
    |
    | When null, this defaults to url('/') — the root URL of the application.
    | Set this to a dedicated auth server URL when your OAuth server lives on a
    | different domain or base path (e.g. 'https://auth.example.com').
    |
    */

    'authorization_server' => null,

];
