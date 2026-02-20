<?php

use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Client\Auth\AuthorizationCodeProvider;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Exceptions\ClientException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;

it('returns cached token', function (): void {
    Cache::put('mcp-auth:test-server:token', 'cached-token', 3600);

    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
    );

    expect($provider->token())->toBe('cached-token');
});

it('returns null when no cached token and no refresh token', function (): void {
    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
    );

    expect($provider->token())->toBeNull();
});

it('returns null when refresh token exists but no token endpoint', function (): void {
    Cache::put('mcp-auth:test-server:refresh_token', 'refresh-token', 86400);

    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
    );

    expect($provider->token())->toBeNull();
});

it('refreshes token when refresh token exists and token endpoint known', function (): void {
    Cache::put('mcp-auth:test-server:refresh_token', 'stored-refresh', 86400);

    $accessToken = Mockery::mock(AccessToken::class);
    $accessToken->shouldReceive('getToken')->andReturn('refreshed-token');
    $accessToken->shouldReceive('getExpires')->andReturn(time() + 3600);
    $accessToken->shouldReceive('getRefreshToken')->andReturn('new-refresh');

    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')
        ->with('refresh_token', ['refresh_token' => 'stored-refresh'])
        ->andReturn($accessToken);

    $provider = Mockery::mock(AuthorizationCodeProvider::class, [
        'client-id', 'https://app.example.com/callback', 'test-server',
        'https://auth.example.com/authorize', 'https://auth.example.com/token',
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $token = $provider->token();

    expect($token)->toBe('refreshed-token');
    expect(Cache::get('mcp-auth:test-server:token'))->toBe('refreshed-token');
    expect(Cache::get('mcp-auth:test-server:refresh_token'))->toBe('new-refresh');
});

it('clears refresh token on refresh failure', function (): void {
    Cache::put('mcp-auth:test-server:refresh_token', 'bad-refresh', 86400);

    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')
        ->andThrow(new RuntimeException('Token expired'));

    $provider = Mockery::mock(AuthorizationCodeProvider::class, [
        'client-id', 'https://app.example.com/callback', 'test-server',
        'https://auth.example.com/authorize', 'https://auth.example.com/token',
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $token = $provider->token();

    expect($token)->toBeNull();
    expect(Cache::get('mcp-auth:test-server:refresh_token'))->toBeNull();
});

it('generates authorization url with pkce', function (): void {
    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
        authorizationEndpoint: 'https://auth.example.com/authorize',
        tokenEndpoint: 'https://auth.example.com/token',
    );

    [$url, $state] = $provider->authorizationUrl();

    expect($url)->toContain('https://auth.example.com/authorize')
        ->and($url)->toContain('client_id=client-id')
        ->and($url)->toContain('redirect_uri=')
        ->and($url)->toContain('code_challenge=')
        ->and($url)->toContain('code_challenge_method=S256')
        ->and($state)->toBeString()
        ->and(strlen($state))->toBe(32);

    expect(Cache::get('mcp-auth:test-server:state'))->toBe($state);
    expect(Cache::get('mcp-auth:test-server:code_verifier'))->toBeString();
});

it('throws authorization required on handle unauthorized', function (): void {
    $discovery = Mockery::mock(AuthServerDiscovery::class);
    $discovery->shouldReceive('discover')
        ->once()
        ->andReturn([
            'token_endpoint' => 'https://auth.example.com/token',
            'authorization_endpoint' => 'https://auth.example.com/authorize',
        ]);

    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
        discovery: $discovery,
    );

    try {
        $provider->handleUnauthorized('Bearer resource_metadata="https://example.com/.well-known/oauth-protected-resource"');
        $this->fail('Expected AuthorizationRequiredException');
    } catch (AuthorizationRequiredException $authorizationRequiredException) {
        expect($authorizationRequiredException->authorizationUrl)->toContain('https://auth.example.com/authorize')
            ->and($authorizationRequiredException->state)->toBeString()
            ->and($authorizationRequiredException->provider)->toBe($provider);
    }
});

it('skips discovery when endpoints are configured', function (): void {
    $discovery = Mockery::mock(AuthServerDiscovery::class);
    $discovery->shouldNotReceive('discover');

    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
        authorizationEndpoint: 'https://auth.example.com/authorize',
        tokenEndpoint: 'https://auth.example.com/token',
        discovery: $discovery,
    );

    try {
        $provider->handleUnauthorized('Bearer realm="example"');
        $this->fail('Expected AuthorizationRequiredException');
    } catch (AuthorizationRequiredException $authorizationRequiredException) {
        expect($authorizationRequiredException->authorizationUrl)->toContain('https://auth.example.com/authorize');
    }
});

it('exchanges code for tokens', function (): void {
    Cache::put('mcp-auth:test-server:state', 'valid-state', 600);
    Cache::put('mcp-auth:test-server:code_verifier', 'test-verifier', 600);

    $accessToken = Mockery::mock(AccessToken::class);
    $accessToken->shouldReceive('getToken')->andReturn('exchanged-token');
    $accessToken->shouldReceive('getExpires')->andReturn(time() + 3600);
    $accessToken->shouldReceive('getRefreshToken')->andReturn('refresh-token-123');

    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')
        ->with('authorization_code', Mockery::on(fn ($params): bool => $params['code'] === 'auth-code' && $params['code_verifier'] === 'test-verifier'))
        ->andReturn($accessToken);

    $provider = Mockery::mock(AuthorizationCodeProvider::class, [
        'client-id', 'https://app.example.com/callback', 'test-server',
        'https://auth.example.com/authorize', 'https://auth.example.com/token',
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $token = $provider->exchangeCode('auth-code', 'valid-state');

    expect($token)->toBe('exchanged-token');
    expect(Cache::get('mcp-auth:test-server:token'))->toBe('exchanged-token');
    expect(Cache::get('mcp-auth:test-server:refresh_token'))->toBe('refresh-token-123');
});

it('exchanges code without refresh token', function (): void {
    Cache::put('mcp-auth:test-server:state', 'valid-state', 600);
    Cache::put('mcp-auth:test-server:code_verifier', 'test-verifier', 600);

    $accessToken = Mockery::mock(AccessToken::class);
    $accessToken->shouldReceive('getToken')->andReturn('exchanged-token');
    $accessToken->shouldReceive('getExpires')->andReturn(null);
    $accessToken->shouldReceive('getRefreshToken')->andReturn(null);

    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')->andReturn($accessToken);

    $provider = Mockery::mock(AuthorizationCodeProvider::class, [
        'client-id', 'https://app.example.com/callback', 'test-server',
        'https://auth.example.com/authorize', 'https://auth.example.com/token',
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $token = $provider->exchangeCode('auth-code', 'valid-state');

    expect($token)->toBe('exchanged-token');
    expect(Cache::get('mcp-auth:test-server:refresh_token'))->toBeNull();
});

it('throws on exchange code with invalid state', function (): void {
    Cache::put('mcp-auth:test-server:state', 'valid-state', 600);

    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
        authorizationEndpoint: 'https://auth.example.com/authorize',
        tokenEndpoint: 'https://auth.example.com/token',
    );

    $provider->exchangeCode('auth-code', 'invalid-state');
})->throws(ClientException::class, 'Invalid OAuth state parameter.');

it('throws on exchange code when code verifier is missing', function (): void {
    Cache::put('mcp-auth:test-server:state', 'valid-state', 600);

    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
        authorizationEndpoint: 'https://auth.example.com/authorize',
        tokenEndpoint: 'https://auth.example.com/token',
    );

    $provider->exchangeCode('auth-code', 'valid-state');
})->throws(ClientException::class, 'PKCE code verifier not found.');

it('wraps oauth exceptions in client exception on code exchange', function (): void {
    Cache::put('mcp-auth:test-server:state', 'valid-state', 600);
    Cache::put('mcp-auth:test-server:code_verifier', 'test-verifier', 600);

    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')
        ->andThrow(new RuntimeException('Invalid grant'));

    $provider = Mockery::mock(AuthorizationCodeProvider::class, [
        'client-id', 'https://app.example.com/callback', 'test-server',
        'https://auth.example.com/authorize', 'https://auth.example.com/token',
    ])->makePartial()->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('createOAuthProvider')->andReturn($oauthProvider);

    $provider->exchangeCode('auth-code', 'valid-state');
})->throws(ClientException::class, 'Failed to exchange authorization code: Invalid grant');

it('exception contains authorization url and state and provider', function (): void {
    $provider = new AuthorizationCodeProvider(
        clientId: 'client-id',
        redirectUri: 'https://app.example.com/callback',
        serverName: 'test-server',
        authorizationEndpoint: 'https://auth.example.com/authorize',
        tokenEndpoint: 'https://auth.example.com/token',
    );

    $exception = new AuthorizationRequiredException(
        'https://auth.example.com/authorize?client_id=client-id',
        'state-value',
        $provider,
    );

    expect($exception->authorizationUrl)->toBe('https://auth.example.com/authorize?client_id=client-id')
        ->and($exception->state)->toBe('state-value')
        ->and($exception->provider)->toBe($provider)
        ->and($exception)->toBeInstanceOf(ClientException::class)
        ->and($exception->getMessage())->toBe('Authorization required. Redirect user to the authorization URL.');
});
