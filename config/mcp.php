<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allow All Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Whether to restrict OAuth client redirect URIs to specific domains. When
    | enabled, all redirect domains will be permitted. When disabled, only
    | domains listed in the "allowed_redirect_domains" array may be used.
    |
    */

    'allow_all_redirect_domains' => true,

    /*
    |--------------------------------------------------------------------------
    | Allowed Redirect Domains
    |--------------------------------------------------------------------------
    |
    | List of domains that OAuth clients are permitted to use for redirect URIs
    | when "allow_all_redirect_domains" is set to false. Each domain should
    | be specified with a protocol (for example - "https://example.com").
    |
    */

    'allowed_redirect_domains' => [],
];
