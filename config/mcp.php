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
    | JSON Response Formatting
    |--------------------------------------------------------------------------
    |
    | This option controls whether JSON responses use pretty-printing with
    | indentation and line breaks. Pretty-printing improves readability
    | for humans, but will increase your agent's token usage by ~40%.
    |
    */

    'pretty_json' => env('MCP_PRETTY_JSON', true),

];
