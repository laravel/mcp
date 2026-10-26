<?php

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\OAuth\AuthServerDiscovery;
use Laravel\Mcp\Client\OAuth\DynamicClientRegistration;

it('limits discovery requests to http and https with a capped response size', function (): void {
    $options = [];

    Http::fake(function ($request, array $requestOptions) use (&$options): void {
        $options[] = $requestOptions['curl'] ?? null;
    });

    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    (new AuthServerDiscovery)->discover('https://mcp.test/mcp');

    expect($options)->toHaveCount(2)->each->toMatchArray([
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_MAXFILESIZE => 1_048_576,
    ]);
});

it('limits registration requests to http and https', function (): void {
    $options = null;

    Http::fake(function ($request, array $requestOptions) use (&$options): void {
        $options = $requestOptions['curl'] ?? null;
    });

    Http::fake(['https://auth.test/register' => Http::response(['client_id' => 'client-1'])]);

    (new DynamicClientRegistration)->register('https://auth.test/register', 'https://app.test/callback');

    expect($options[CURLOPT_PROTOCOLS])->toBe(CURLPROTO_HTTP | CURLPROTO_HTTPS);
});
