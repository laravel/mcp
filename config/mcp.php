<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Protocol Version
    |--------------------------------------------------------------------------
    |
    | The MCP protocol version that the client will use when connecting
    | to external MCP servers. This should match a version supported
    | by the servers you are connecting to.
    |
    */

    'protocol_version' => '2025-11-25',

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
    | Here you may configure the OAuth authorization server issuer identifier
    | per RFC 8414. This value appears in your protected resource and auth
    | server metadata endpoints. When null, this defaults to `url('/')`.
    |
    */

    'authorization_server' => null,

    /*
    |--------------------------------------------------------------------------
    | MCP Servers (Client Connections)
    |--------------------------------------------------------------------------
    |
    | Define external MCP servers that your application can connect to as
    | a client. Each entry configures a named connection with its transport
    | type, connection details, and optional caching.
    |
    */

    'servers' => [
        // 'example' => [
        //     'transport' => 'stdio',
        //     'command' => 'php',
        //     'args' => ['artisan', 'mcp:start', 'example'],
        //     'timeout' => 30,
        //     'cache_ttl' => 300,
        // ],
        // 'remote' => [
        //     'transport' => 'http',
        //     'url' => 'https://example.com/mcp',
        //     'headers' => [],
        //     'timeout' => 30,
        //     'cache_ttl' => 300,
        // ],
        // 'remote-oauth' => [
        //     'transport' => 'http',
        //     'url' => 'https://example.com/mcp',
        //     'timeout' => 30,
        //     'auth' => [
        //         'type' => 'client_credentials',
        //         'client_id' => env('MCP_CLIENT_ID'),
        //         'client_secret' => env('MCP_CLIENT_SECRET'),
        //         'scope' => 'mcp:use',
        //         // 'token_endpoint' => 'https://auth.example.com/token',
        //     ],
        // ],
        // 'oauth-server' => [
        //     'transport' => 'http',
        //     'url' => 'https://example.com/mcp',
        //     'auth' => [
        //         'type' => 'authorization_code',
        //         'client_id' => env('MCP_CLIENT_ID'),
        //         'redirect_uri' => env('MCP_REDIRECT_URI'),
        //         'scope' => 'mcp:use',
        //     ],
        // ],
    ],

];
