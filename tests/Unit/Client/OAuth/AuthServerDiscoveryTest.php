<?php

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\AuthServerDiscovery;
use Laravel\Mcp\Client\OAuth\DiscoveryResult;

function resolveHosts(array $hosts): void
{
    app()->instance(AuthServerDiscovery::RESOLVER_BINDING, fn (string $host): array => $hosts[$host] ?? []);
}

function fakeAuthServer(string $resource = 'https://mcp.test', string $authorizationEndpoint = 'https://auth.test/authorize'): void
{
    Http::fake([
        $resource.'/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => $authorizationEndpoint,
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);
}

it('rejects hosts that resolve to non public addresses', function (string $address): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'meta.test' => ['93.184.216.34', $address]]);

    Http::fake();

    expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp', 'https://meta.test/prm'))
        ->toThrow(OAuthException::class, 'cannot use a private or internal host');

    Http::assertNothingSent();
})->with([
    'rfc1918 10/8' => '10.0.0.5',
    'rfc1918 172.16/12' => '172.16.0.1',
    'rfc1918 192.168/16' => '192.168.1.1',
    'cloud metadata' => '169.254.169.254',
    'loopback' => '127.0.0.1',
    'carrier grade nat' => '100.64.0.1',
    'ipv4 multicast' => '224.0.0.1',
    'ipv6 loopback' => '::1',
    'ipv6 link local' => 'fe80::1',
    'ipv6 unique local' => 'fc00::1',
    'ipv4 mapped ipv6' => '::ffff:10.0.0.1',
    'ipv6 multicast' => 'ff02::1',
    'nat64' => '64:ff9b::a9fe:a9fe',
    'local use nat64' => '64:ff9b:1::a9fe:a9fe',
    '6to4' => '2002:7f00:1::',
    'teredo' => '2001:0:4136:e378:8000:63bf:3fff:fdd2',
]);

it('allows hosts that resolve to an ipv4 mapped public address', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'auth.test' => ['::ffff:93.184.216.34']]);

    fakeAuthServer();

    expect((new AuthServerDiscovery)->discover('https://mcp.test/mcp')->server->issuer)
        ->toBe('https://auth.test');
});

it('rejects hosts that do not resolve', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34']]);

    expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp', 'https://meta.test/prm'))
        ->toThrow(OAuthException::class, 'could not be resolved');
});

it('allows an internal endpoint on the same host as the resource', function (): void {
    resolveHosts(['intranet.test' => ['10.0.0.5'], 'auth.test' => ['93.184.216.34']]);

    fakeAuthServer('https://intranet.test');

    expect((new AuthServerDiscovery)->discover('https://intranet.test/mcp')->server->issuer)
        ->toBe('https://auth.test');
});

it('rejects an internal endpoint on another port of the resource host', function (): void {
    resolveHosts(['intranet.test' => ['10.0.0.5']]);

    expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://intranet.test/mcp', 'https://intranet.test:2375/prm'))
        ->toThrow(OAuthException::class, 'cannot use a private or internal host');
});

it('looks up each host once per discovery', function (): void {
    $lookups = [];

    app()->instance(AuthServerDiscovery::RESOLVER_BINDING, function (string $host) use (&$lookups): array {
        $lookups[$host] = ($lookups[$host] ?? 0) + 1;

        return ['93.184.216.34'];
    });

    fakeAuthServer();

    (new AuthServerDiscovery)->discover('https://mcp.test/mcp');

    expect($lookups)->toBe(['mcp.test' => 1, 'auth.test' => 1]);
});

it('does not resolve the authorization endpoint the browser opens', function (): void {
    resolveHosts([
        'mcp.test' => ['93.184.216.34'],
        'auth.test' => ['93.184.216.34'],
        'login.corp.test' => ['10.0.0.5'],
    ]);

    fakeAuthServer(authorizationEndpoint: 'https://login.corp.test/authorize');

    expect((new AuthServerDiscovery)->discover('https://mcp.test/mcp')->server->authorizationEndpoint)
        ->toBe('https://login.corp.test/authorize');
});

it('skips resolution when an http proxy is configured', function (): void {
    resolveHosts([]);
    putenv('HTTPS_PROXY=http://proxy.test:3128');

    fakeAuthServer();

    try {
        expect((new AuthServerDiscovery)->discover('https://mcp.test/mcp')->server->issuer)->toBe('https://auth.test')
            ->and(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp', 'https://127.0.0.1:9200/prm'))
            ->toThrow(OAuthException::class, 'cannot use a private or internal host');
    } finally {
        putenv('HTTPS_PROXY');
    }
});
