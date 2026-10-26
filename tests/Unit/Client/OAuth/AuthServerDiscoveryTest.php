<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\AuthServerDiscovery;
use Laravel\Mcp\Client\OAuth\DiscoveryResult;

function resolveHosts(array $hosts): void
{
    AuthServerDiscovery::resolveHostsUsing(fn (string $host): array => $hosts[$host] ?? []);
}

function fakeAuthServer(
    string $resource = 'https://mcp.test',
    string $authorizationEndpoint = 'https://auth.test/authorize',
    ?string $registrationEndpoint = null,
): void {
    Http::fake([
        $resource.'/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response(array_filter([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => $authorizationEndpoint,
            'token_endpoint' => 'https://auth.test/token',
            'registration_endpoint' => $registrationEndpoint,
        ])),
    ]);
}

it('rejects hosts that resolve to non public addresses', function (string $address): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'meta.test' => ['93.184.216.34', $address]]);

    Http::fake();

    expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp', 'https://meta.test/prm'))
        ->toThrow(OAuthException::class, 'cannot use a private, internal, or unresolvable host');

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
    'benchmarking' => '198.18.0.1',
    'ietf protocol assignments' => '192.0.0.1',
    'ipv4 compatible ipv6' => '::7f00:1',
    'ipv4 translated ipv6' => '::ffff:0:7f00:1',
    'ipv6 site local' => 'fec0::1',
]);

it('rejects internal endpoints when the resource also resolves to public addresses', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34', '10.0.0.1'], 'meta.test' => ['10.0.0.5']]);

    Http::fake();

    expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp', 'https://meta.test/prm'))
        ->toThrow(OAuthException::class, 'cannot use a private, internal, or unresolvable host');

    Http::assertNothingSent();
});

it('allows hosts that resolve to a wrapped public ipv4 address', function (string $address): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'auth.test' => [$address]]);

    fakeAuthServer();

    expect((new AuthServerDiscovery)->discover('https://mcp.test/mcp')->server->issuer)
        ->toBe('https://auth.test');
})->with([
    'ipv4 mapped' => '::ffff:93.184.216.34',
    'nat64' => '64:ff9b::5db8:d822',
]);

it('rejects hosts that do not resolve', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34']]);

    expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp', 'https://meta.test/prm'))
        ->toThrow(OAuthException::class, 'cannot use a private, internal, or unresolvable host');
});

it('allows an internal endpoint on the same host as the resource', function (): void {
    resolveHosts(['intranet.test' => ['10.0.0.5'], 'auth.test' => ['93.184.216.34']]);

    fakeAuthServer('https://intranet.test');

    expect((new AuthServerDiscovery)->discover('https://intranet.test/mcp')->server->issuer)
        ->toBe('https://auth.test');
});

it('allows internal endpoints on other hosts when the resource is internal', function (): void {
    resolveHosts(['mcp.test' => ['127.0.0.1'], 'auth.test' => ['127.0.0.1']]);

    fakeAuthServer();

    expect((new AuthServerDiscovery)->discover('https://mcp.test/mcp')->server->issuer)
        ->toBe('https://auth.test');
});

it('rejects a registration endpoint on an internal host', function (): void {
    resolveHosts([
        'mcp.test' => ['93.184.216.34'],
        'auth.test' => ['93.184.216.34'],
        'register.test' => ['10.0.0.5'],
    ]);

    fakeAuthServer(registrationEndpoint: 'https://register.test/register');

    expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp'))
        ->toThrow(OAuthException::class, 'cannot use a private, internal, or unresolvable host');
});

it('pins each request to the addresses that were checked', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'auth.test' => ['93.184.216.35', '2606:4700::1111']]);

    $pins = [];

    Http::fake(function (Request $request, array $options) use (&$pins): void {
        $pins[$request->url()] = $options['curl'][CURLOPT_RESOLVE] ?? null;
    });

    fakeAuthServer();

    (new AuthServerDiscovery)->discover('https://mcp.test/mcp');

    expect($pins)->toBe([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => ['mcp.test:443:93.184.216.34'],
        'https://auth.test/.well-known/oauth-authorization-server' => ['auth.test:443:93.184.216.35,[2606:4700::1111]'],
    ]);
});

it('looks up each host once per discovery', function (): void {
    $lookups = [];

    AuthServerDiscovery::resolveHostsUsing(function (string $host) use (&$lookups): array {
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

    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.example.com'],
        ]),
        'https://auth.example.com/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.example.com',
            'authorization_endpoint' => 'https://auth.example.com/authorize',
            'token_endpoint' => 'https://auth.example.com/token',
        ]),
    ]);

    try {
        expect((new AuthServerDiscovery)->discover('https://mcp.example.com/mcp')->server->issuer)->toBe('https://auth.example.com')
            ->and(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.example.com/mcp', 'https://127.0.0.1:9200/prm'))
            ->toThrow(OAuthException::class, 'cannot use a private, internal, or unresolvable host');
    } finally {
        putenv('HTTPS_PROXY');
    }
});

it('rejects local hostnames when an http proxy is configured', function (string $host): void {
    resolveHosts([]);
    putenv('HTTPS_PROXY=http://proxy.test:3128');

    Http::fake();

    try {
        expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp', "https://{$host}/prm"))
            ->toThrow(OAuthException::class, 'cannot use a private, internal, or unresolvable host');

        Http::assertNothingSent();
    } finally {
        putenv('HTTPS_PROXY');
    }
})->with([
    'localhost subdomain' => 'app.localhost',
    'trailing dot localhost' => 'localhost.',
    'mdns' => 'printer.local',
    'internal' => 'redis.internal',
    'uppercase internal' => 'METADATA.GOOGLE.INTERNAL',
    'test' => 'auth.test',
]);

it('still resolves hosts when only an http proxy is configured', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'meta.test' => ['10.0.0.5']]);
    putenv('HTTP_PROXY=http://proxy.test:3128');

    Http::fake();

    try {
        expect(fn (): DiscoveryResult => (new AuthServerDiscovery)->discover('https://mcp.test/mcp', 'https://meta.test/prm'))
            ->toThrow(OAuthException::class, 'cannot use a private, internal, or unresolvable host');
    } finally {
        putenv('HTTP_PROXY');
    }
});
