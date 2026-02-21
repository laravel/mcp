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
    ],

];
