<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Auth\AuthServerMetadata;
use Laravel\Mcp\Client\Auth\ProtectedResourceMetadata;
use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;
use Laravel\Mcp\Exceptions\DiscoveryException;

it('uses the resource_metadata URL when the WWW-Authenticate header advertises one', function (): void {
    Http::fake([
        'https://mcp.example.com/custom-prm' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
            'scopes_supported' => ['mcp:read'],
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    $challenge = WwwAuthenticateChallenge::parse('Bearer resource_metadata="https://mcp.example.com/custom-prm"');

    $metadata = (new AuthServerDiscovery)->discoverProtectedResource('https://mcp.example.com/mcp', $challenge);

    expect($metadata->primaryAuthorizationServer())->toBe('https://auth.example.com')
        ->and($metadata->scopesSupported)->toBe(['mcp:read']);
});

it('falls back to the path-insertion PRM URL before the root PRM URL', function (): void {
    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource/mcp' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ]), 200, ['Content-Type' => 'application/json']),
        'https://mcp.example.com/.well-known/oauth-protected-resource' => Http::response('', 404),
    ]);

    $metadata = (new AuthServerDiscovery)->discoverProtectedResource('https://mcp.example.com/mcp');

    expect($metadata->primaryAuthorizationServer())->toBe('https://auth.example.com');
    Http::assertSent(fn ($request): bool => str_ends_with((string) $request->url(), '/.well-known/oauth-protected-resource/mcp'));
});

it('falls back to the root PRM URL when the path variant 404s', function (): void {
    Http::fakeSequence('https://mcp.example.com/*')
        ->push('', 404)
        ->push(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ]), 200, ['Content-Type' => 'application/json']);

    $metadata = (new AuthServerDiscovery)->discoverProtectedResource('https://mcp.example.com/mcp');

    expect($metadata->primaryAuthorizationServer())->toBe('https://auth.example.com');
});

it('throws DiscoveryException when no PRM endpoint responds', function (): void {
    Http::fake(['*' => Http::response('', 404)]);

    expect(fn (): ProtectedResourceMetadata => (new AuthServerDiscovery)->discoverProtectedResource('https://mcp.example.com/mcp'))
        ->toThrow(DiscoveryException::class, 'Unable to discover protected resource metadata');
});

it('throws DiscoveryException when PRM returns 5xx', function (): void {
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(fn (): ProtectedResourceMetadata => (new AuthServerDiscovery)->discoverProtectedResource('https://mcp.example.com/mcp'))
        ->toThrow(DiscoveryException::class, 'HTTP status [500]');
});

it('throws DiscoveryException when PRM lacks authorization_servers', function (): void {
    Http::fake([
        '*' => Http::response(json_encode(['resource' => 'https://mcp.example.com/mcp']), 200, ['Content-Type' => 'application/json']),
    ]);

    expect(fn (): ProtectedResourceMetadata => (new AuthServerDiscovery)->discoverProtectedResource('https://mcp.example.com/mcp'))
        ->toThrow(DiscoveryException::class, 'authorization_servers');
});

it('throws DiscoveryException when PRM JSON is malformed', function (): void {
    Http::fake([
        '*' => Http::response('not-json', 200, ['Content-Type' => 'application/json']),
    ]);

    expect(fn (): ProtectedResourceMetadata => (new AuthServerDiscovery)->discoverProtectedResource('https://mcp.example.com/mcp'))
        ->toThrow(DiscoveryException::class, 'not valid JSON');
});

it('rejects http URLs for non-loopback hosts', function (): void {
    expect(fn (): ProtectedResourceMetadata => (new AuthServerDiscovery)->discoverProtectedResource('http://mcp.example.com/mcp'))
        ->toThrow(DiscoveryException::class, 'must use HTTPS');
});

it('allows http loopback hosts during discovery', function (string $host): void {
    Http::fake([
        '*' => Http::response(json_encode([
            'resource' => "http://{$host}/mcp",
            'authorization_servers' => ['https://auth.example.com'],
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    $metadata = (new AuthServerDiscovery)->discoverProtectedResource("http://{$host}/mcp");

    expect($metadata->primaryAuthorizationServer())->toBe('https://auth.example.com');
})->with(['localhost', '127.0.0.1']);

it('walks AS metadata endpoints in path-insertion-first order', function (): void {
    Http::fakeSequence('https://auth.example.com/*')
        ->push('', 404)
        ->push('', 404)
        ->push(json_encode([
            'issuer' => 'https://auth.example.com/tenant',
            'token_endpoint' => 'https://auth.example.com/tenant/token',
        ]), 200, ['Content-Type' => 'application/json']);

    $metadata = (new AuthServerDiscovery)->discoverAuthServer('https://auth.example.com/tenant');

    expect($metadata->tokenEndpoint)->toBe('https://auth.example.com/tenant/token');
});

it('throws DiscoveryException when AS metadata is missing the token endpoint', function (): void {
    Http::fake([
        '*' => Http::response(json_encode([
            'issuer' => 'https://auth.example.com',
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    expect(fn (): AuthServerMetadata => (new AuthServerDiscovery)->discoverAuthServer('https://auth.example.com'))
        ->toThrow(DiscoveryException::class, 'token_endpoint');
});

it('throws DiscoveryException when the AS token endpoint is not HTTPS', function (): void {
    Http::fake([
        '*' => Http::response(json_encode([
            'issuer' => 'https://auth.example.com',
            'token_endpoint' => 'http://auth.example.com/token',
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    expect(fn (): AuthServerMetadata => (new AuthServerDiscovery)->discoverAuthServer('https://auth.example.com'))
        ->toThrow(DiscoveryException::class, 'must use HTTPS');
});

it('throws DiscoveryException when AS metadata cannot be fetched', function (): void {
    Http::fake(['*' => Http::response('', 404)]);

    expect(fn (): AuthServerMetadata => (new AuthServerDiscovery)->discoverAuthServer('https://auth.example.com'))
        ->toThrow(DiscoveryException::class, 'Unable to discover authorization server metadata');
});
