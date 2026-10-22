<?php

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\AuthServerDiscovery;

function resolveHosts(array $hosts): void
{
    app()->instance(AuthServerDiscovery::RESOLVER_BINDING, fn (string $host): array => $hosts[$host] ?? []);
}

function pinned(string $url, string $resourceUrl): ?array
{
    return (new AuthServerDiscovery)->request($url, $resourceUrl)->getOptions()['curl'] ?? null;
}

it('pins a validated host to its resolved addresses', function (): void {
    resolveHosts(['auth.test' => ['93.184.216.34', '2606:2800:220:1::1']]);

    expect(pinned('https://auth.test/token', 'https://mcp.test/mcp'))
        ->toBe([CURLOPT_RESOLVE => ['auth.test:443:93.184.216.34,[2606:2800:220:1::1]']]);
});

it('pins the explicit port of the endpoint', function (): void {
    resolveHosts(['auth.test' => ['93.184.216.34']]);

    expect(pinned('https://auth.test:8443/token', 'https://mcp.test/mcp'))
        ->toBe([CURLOPT_RESOLVE => ['auth.test:8443:93.184.216.34']]);
});

it('does not pin loopback hosts or ip literals', function (string $url): void {
    expect(pinned($url, $url))->toBeNull();
})->with([
    'localhost' => 'http://localhost:8000/token',
    'ipv4 loopback' => 'http://127.0.0.1/token',
    'public ipv4 literal' => 'https://93.184.216.34/token',
]);

it('rejects hosts that resolve to non public addresses', function (string $address): void {
    resolveHosts(['auth.test' => ['93.184.216.34', $address]]);

    expect(fn (): ?array => pinned('https://auth.test/token', 'https://mcp.test/mcp'))
        ->toThrow(OAuthException::class, 'cannot use a private or internal host');
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

it('rejects hosts that do not resolve', function (): void {
    resolveHosts([]);

    expect(fn (): ?array => pinned('https://auth.test/token', 'https://mcp.test/mcp'))
        ->toThrow(OAuthException::class, 'could not be resolved');
});

it('allows an internal endpoint on the same host as the resource', function (): void {
    resolveHosts(['intranet.test' => ['10.0.0.5']]);

    expect(pinned('https://intranet.test/token', 'https://intranet.test/mcp'))
        ->toBe([CURLOPT_RESOLVE => ['intranet.test:443:10.0.0.5']]);
});

it('uses a single lookup so a rebound host cannot change what was validated', function (): void {
    $lookups = 0;

    app()->instance(AuthServerDiscovery::RESOLVER_BINDING, function (string $host) use (&$lookups): array {
        return ++$lookups === 1 ? ['93.184.216.34'] : ['169.254.169.254'];
    });

    $discovery = new AuthServerDiscovery;

    $first = $discovery->request('https://auth.test/token', 'https://mcp.test/mcp')->getOptions()['curl'];
    $second = $discovery->request('https://auth.test/token', 'https://mcp.test/mcp')->getOptions()['curl'];

    expect($first)->toBe($second)
        ->and($first[CURLOPT_RESOLVE])->toBe(['auth.test:443:93.184.216.34'])
        ->and($lookups)->toBe(1);
});

it('pins every request made during discovery', function (): void {
    $pinned = [];

    Http::fake(function ($request, array $options) use (&$pinned): void {
        $pinned[$request->toPsrRequest()->getUri()->getHost()][] = $options['curl'][CURLOPT_RESOLVE] ?? null;
    });

    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'resource' => 'https://mcp.test/mcp',
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    (new AuthServerDiscovery)->discover('https://mcp.test/mcp');

    expect($pinned)->toBe([
        'mcp.test' => [['mcp.test:443:93.184.216.34']],
        'auth.test' => [['auth.test:443:93.184.216.34']],
    ]);
});

it('validates and pins every redirect hop of the resource probe', function (): void {
    resolveHosts([
        'mcp.test' => ['93.184.216.34'],
        'meta.test' => ['93.184.216.34'],
        'edge.test' => ['93.184.216.35'],
    ]);

    $pinned = [];

    Http::fake(function ($request, array $options) use (&$pinned): void {
        $pinned[] = $options['curl'][CURLOPT_RESOLVE][0] ?? null;
    });

    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 307, ['Location' => 'https://edge.test/mcp']),
        'https://edge.test/mcp' => Http::response('', 401, [
            'WWW-Authenticate' => 'Bearer resource_metadata="https://meta.test/prm"',
        ]),
    ]);

    (new AuthServerDiscovery)->requireAdvertised('https://mcp.test/mcp', 'https://meta.test/prm');

    expect($pinned)->toBe(['mcp.test:443:93.184.216.34', 'edge.test:443:93.184.216.35']);
});

it('rejects a resource probe redirected to an internal host', function (): void {
    resolveHosts([
        'mcp.test' => ['93.184.216.34'],
        'meta.test' => ['93.184.216.34'],
        'internal.test' => ['10.0.0.5'],
    ]);

    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 302, ['Location' => 'https://internal.test/admin']),
    ]);

    expect(fn () => (new AuthServerDiscovery)->requireAdvertised('https://mcp.test/mcp', 'https://meta.test/prm'))
        ->toThrow(OAuthException::class, 'cannot use a private or internal host');

    Http::assertNotSent(fn ($request): bool => $request->url() === 'https://internal.test/admin');
});

it('rejects a resource probe that redirects too many times', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'meta.test' => ['93.184.216.34']]);

    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 302, ['Location' => '/mcp']),
    ]);

    expect(fn () => (new AuthServerDiscovery)->requireAdvertised('https://mcp.test/mcp', 'https://meta.test/prm'))
        ->toThrow(OAuthException::class, 'Unable to reach the resource');

    Http::assertSentCount(6);
});

it('rejects an internal endpoint on another port of the resource host', function (): void {
    resolveHosts(['intranet.test' => ['10.0.0.5']]);

    expect(fn (): ?array => pinned('https://intranet.test:2375/token', 'https://intranet.test/mcp'))
        ->toThrow(OAuthException::class, 'cannot use a private or internal host');
});

it('does not resolve the authorization endpoint the browser opens', function (): void {
    resolveHosts([
        'mcp.test' => ['93.184.216.34'],
        'auth.test' => ['93.184.216.34'],
        'login.corp.test' => ['10.0.0.5'],
    ]);

    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://login.corp.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    expect((new AuthServerDiscovery)->discover('https://mcp.test/mcp')->server->authorizationEndpoint)
        ->toBe('https://login.corp.test/authorize');
});

it('skips resolution and pinning when an http proxy is configured', function (): void {
    resolveHosts([]);
    putenv('HTTPS_PROXY=http://proxy.test:3128');

    try {
        expect(pinned('https://auth.test/token', 'https://mcp.test/mcp'))->toBeNull()
            ->and(fn (): ?array => pinned('https://127.0.0.1:9200/token', 'https://mcp.test/mcp'))
            ->toThrow(OAuthException::class, 'cannot use a private or internal host');
    } finally {
        putenv('HTTPS_PROXY');
    }
});

it('only sends custom headers to the origin of the resource', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'meta.test' => ['93.184.216.34'], 'edge.test' => ['93.184.216.35']]);

    $sent = [];

    Http::fake(function ($request) use (&$sent): void {
        $sent[$request->toPsrRequest()->getUri()->getHost()] = $request->header('X-Api-Key');
    });

    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 302, ['Location' => 'https://edge.test/mcp']),
        'https://edge.test/mcp' => Http::response('', 401),
    ]);

    expect(fn () => (new AuthServerDiscovery)->requireAdvertised('https://mcp.test/mcp', 'https://meta.test/prm', ['X-Api-Key' => 'secret']))
        ->toThrow(OAuthException::class);

    expect($sent)->toBe(['mcp.test' => ['secret'], 'edge.test' => []]);
});

it('follows a see other redirect of the resource probe with a get', function (): void {
    resolveHosts(['mcp.test' => ['93.184.216.34'], 'meta.test' => ['93.184.216.34']]);

    Http::fake([
        'https://mcp.test/mcp' => Http::response('', 303, ['Location' => '/status']),
        'https://mcp.test/status' => Http::response('', 401, [
            'WWW-Authenticate' => 'Bearer resource_metadata="https://meta.test/prm"',
        ]),
    ]);

    (new AuthServerDiscovery)->requireAdvertised('https://mcp.test/mcp', 'https://meta.test/prm');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://mcp.test/status' && $request->method() === 'GET');
});
