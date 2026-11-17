<?php

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\OAuth\AuthServerDiscovery;
use Laravel\Mcp\Client\OAuth\DynamicClientRegistration;

function expectLimitedOAuthOptions(array $options, bool $checksSize = true): void
{
    if (! defined(RequestOptions::class.'::PROTOCOLS')) {
        expect($options['curl'][CURLOPT_PROTOCOLS])->toBe(CURLPROTO_HTTP | CURLPROTO_HTTPS);

        if ($checksSize) {
            expect($options['curl'][CURLOPT_MAXFILESIZE])->toBe(1_048_576);
        }

        return;
    }

    expect($options['protocols'])->toBe(['http', 'https']);

    if ($checksSize) {
        expect(($options['progress'])(0, 1_048_576))->toBeFalse()
            ->and(($options['progress'])(0, 1_048_577))->toBeTrue()
            ->and(($options['progress'])(1_048_577, 0))->toBeTrue();
    }
}

it('limits discovery requests to http and https with a capped response size', function (): void {
    $options = [];

    Http::fake(function ($request, array $requestOptions) use (&$options): void {
        $options[] = $requestOptions;
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

    expect($options)->toHaveCount(2);

    foreach ($options as $requestOptions) {
        expectLimitedOAuthOptions($requestOptions);
    }
});

it('limits registration requests to http and https', function (): void {
    $options = [];

    Http::fake(function ($request, array $requestOptions) use (&$options): void {
        $options = $requestOptions;
    });

    Http::fake(['https://auth.test/register' => Http::response(['client_id' => 'client-1'])]);

    (new DynamicClientRegistration)->register('https://auth.test/register', 'https://app.test/callback');

    expectLimitedOAuthOptions($options, checksSize: false);
});
