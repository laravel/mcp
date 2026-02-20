<?php

use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Auth\ClientCredentialsProvider;
use Laravel\Mcp\Client\Exceptions\ClientException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;

it('returns cached token', function (): void {
    Cache::put('mcp-auth:test-server:token', 'cached-token', 3600);

    $provider = new ClientCredentialsProvider(
        clientId: 'client-id',
        clientSecret: 'client-secret',
        serverName: 'test-server',
    );

    expect($provider->token())->toBe('cached-token');
});

it('returns null when no cached token and no token endpoint', function (): void {
    $provider = new ClientCredentialsProvider(
        clientId: 'client-id',
        clientSecret: 'client-secret',
        serverName: 'test-server',
    );

    expect($provider->token())->toBeNull();
});

it('acquires token when token endpoint is set and no cache', function (): void {
    $accessToken = Mockery::mock(AccessToken::class);
    $accessToken->shouldReceive('getToken')->andReturn('new-access-token');
    $accessToken->shouldReceive('getExpires')->andReturn(time() + 3600);

    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')
        ->with('client_credentials', ['scope' => 'mcp:use'])
        ->andReturn($accessToken);

    $provider = Mockery::mock(ClientCredentialsProvider::class, [
        'client-id', 'client-secret', 'test-server', 'https://auth.example.com/token',
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $token = $provider->token();

    expect($token)->toBe('new-access-token');
    expect(Cache::get('mcp-auth:test-server:token'))->toBe('new-access-token');
});

it('caches token with default ttl when expires is null', function (): void {
    $accessToken = Mockery::mock(AccessToken::class);
    $accessToken->shouldReceive('getToken')->andReturn('no-expiry-token');
    $accessToken->shouldReceive('getExpires')->andReturn(null);

    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')->andReturn($accessToken);

    $provider = Mockery::mock(ClientCredentialsProvider::class, [
        'client-id', 'client-secret', 'test-server', 'https://auth.example.com/token',
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $token = $provider->token();

    expect($token)->toBe('no-expiry-token');
    expect(Cache::get('mcp-auth:test-server:token'))->toBe('no-expiry-token');
});

it('runs discovery on handle unauthorized when no token endpoint', function (): void {
    $discovery = Mockery::mock(AuthServerDiscovery::class);
    $discovery->shouldReceive('discover')
        ->once()
        ->with('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"')
        ->andReturn([
            'token_endpoint' => 'https://auth.example.com/token',
        ]);

    $provider = new ClientCredentialsProvider(
        clientId: 'client-id',
        clientSecret: 'client-secret',
        serverName: 'test-server',
        discovery: $discovery,
    );

    expect(fn () => $provider->handleUnauthorized('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"'))
        ->toThrow(\Exception::class);
});

it('skips discovery when token endpoint is configured', function (): void {
    $discovery = Mockery::mock(AuthServerDiscovery::class);
    $discovery->shouldNotReceive('discover');

    $provider = new ClientCredentialsProvider(
        clientId: 'client-id',
        clientSecret: 'client-secret',
        serverName: 'test-server',
        tokenEndpoint: 'https://auth.example.com/token',
        discovery: $discovery,
    );

    expect(fn () => $provider->handleUnauthorized('Bearer realm="example"'))
        ->toThrow(\Exception::class);
});

it('wraps oauth exceptions in client exception', function (): void {
    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')
        ->andThrow(new RuntimeException('Connection refused'));

    $provider = Mockery::mock(ClientCredentialsProvider::class, [
        'client-id', 'client-secret', 'test-server', 'https://auth.example.com/token',
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $provider->token();
})->throws(ClientException::class, 'Failed to acquire access token: Connection refused');

it('acquires token on handle unauthorized with discovery', function (): void {
    $accessToken = Mockery::mock(AccessToken::class);
    $accessToken->shouldReceive('getToken')->andReturn('discovered-token');
    $accessToken->shouldReceive('getExpires')->andReturn(time() + 3600);

    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')->andReturn($accessToken);

    $discovery = Mockery::mock(AuthServerDiscovery::class);
    $discovery->shouldReceive('discover')->andReturn([
        'token_endpoint' => 'https://auth.example.com/token',
    ]);

    $provider = Mockery::mock(ClientCredentialsProvider::class, [
        'client-id', 'client-secret', 'test-server', null, 'mcp:use', $discovery,
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $provider->handleUnauthorized('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');

    expect(Cache::get('mcp-auth:test-server:token'))->toBe('discovered-token');
});
