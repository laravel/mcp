<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Exceptions\OAuthException;
use Laravel\Mcp\Client\OAuth\OAuthClient;
use Laravel\Mcp\Client\OAuth\TokenSet;

function fakeDiscovery(): void
{
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'resource' => 'https://mcp.test/mcp',
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
            'registration_endpoint' => 'https://auth.test/register',
            'code_challenge_methods_supported' => ['S256'],
        ]),
    ]);
}

it('builds an authorization redirect with PKCE and stashes session state', function (): void {
    fakeDiscovery();

    $response = Client::web('https://mcp.test/mcp')
        ->withOAuth(
            clientId: 'client-123',
            scope: 'mcp:use',
            redirectUri: 'https://app.test/callback',
        )
        ->oAuth()
        ->redirect('/dashboard');

    $target = $response->getTargetUrl();
    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($target)->toStartWith('https://auth.test/authorize?')
        ->and($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('client-123')
        ->and($query['redirect_uri'])->toBe('https://app.test/callback')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['scope'])->toBe('mcp:use')
        ->and($query['resource'])->toBe('https://mcp.test/mcp')
        ->and($query)->toHaveKey('code_challenge')
        ->and($query)->toHaveKey('state');

    $stored = Session::get('mcp.oauth.'.sha1('https://mcp.test/mcp'));

    expect($stored['state'])->toBe($query['state'])
        ->and($stored['client_id'])->toBe('client-123')
        ->and($stored['return_to'])->toBe('/dashboard')
        ->and($stored['verifier'])->toBeString();
});

it('merges query params onto an authorization endpoint that already has a query string', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize?audience=api',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect()
        ->getTargetUrl();

    expect(substr_count($target, '?'))->toBe(1);

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($query['audience'])->toBe('api')
        ->and($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('client-123');
});

it('dynamically registers a client when no client id is configured', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/register' => Http::response(['client_id' => 'dcr-999', 'client_secret' => 'shh']),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect();

    $stored = Session::get('mcp.oauth.'.sha1('https://mcp.test/mcp'));

    expect($stored['client_id'])->toBe('dcr-999')
        ->and($stored['client_secret'])->toBe('shh');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.test/register'
        && ($request['redirect_uris'] ?? null) === ['https://app.test/callback']);
});

it('exchanges an authorization code for a token set', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'mcp:use',
        ]),
    ]);

    $key = 'mcp.oauth.'.sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
    ]);

    app()->instance('request', Request::create('https://app.test/callback', 'GET', [
        'code' => 'auth-code',
        'state' => 'the-state',
    ]));

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->callbackToken();

    expect($token)->toBeInstanceOf(TokenSet::class)
        ->and($token->accessToken)->toBe('access-token')
        ->and($token->refreshToken)->toBe('refresh-token')
        ->and($token->expiresAt)->toBeGreaterThan(time());

    expect(Session::has($key))->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.test/token'
        && ($request['grant_type'] ?? null) === 'authorization_code'
        && ($request['code'] ?? null) === 'auth-code'
        && ($request['code_verifier'] ?? null) === 'the-verifier'
        && ($request['resource'] ?? null) === 'https://mcp.test/mcp');
});

it('rejects a mismatched state parameter', function (): void {
    fakeDiscovery();

    $key = 'mcp.oauth.'.sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'expected-state',
        'verifier' => 'verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
    ]);

    app()->instance('request', Request::create('https://app.test/callback', 'GET', [
        'code' => 'auth-code',
        'state' => 'tampered-state',
    ]));

    expect(fn (): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->callbackToken())
        ->toThrow(OAuthException::class, 'state parameter did not match');
});

it('runs the client credentials grant', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response([
            'access_token' => 'machine-token',
            'expires_in' => 7200,
        ]),
    ]);

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'svc', clientSecret: 'secret', scope: 'mcp:use')
        ->oAuth()
        ->clientCredentialsToken();

    expect($token->accessToken)->toBe('machine-token');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.test/token'
        && ($request['grant_type'] ?? null) === 'client_credentials'
        && ($request['client_id'] ?? null) === 'svc'
        && ($request['client_secret'] ?? null) === 'secret');
});

it('throws when the authorization server redirects back with an error', function (): void {
    app()->instance('request', Request::create('https://app.test/callback', 'GET', [
        'error' => 'access_denied',
        'error_description' => 'The user denied the request',
    ]));

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->callbackToken();
})->throws(OAuthException::class, 'The authorization server returned an error [access_denied]: The user denied the request');

it('throws when the authorization callback has no code', function (): void {
    app()->instance('request', Request::create('https://app.test/callback', 'GET'));

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->callbackToken();
})->throws(OAuthException::class, 'The authorization response did not include an authorization code.');

it('refreshes a token using the refresh grant', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response([
            'access_token' => 'fresh-token',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
        ]),
    ]);

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use')
        ->oAuth()
        ->refresh('old-refresh');

    expect($token->accessToken)->toBe('fresh-token')
        ->and($token->refreshToken)->toBe('new-refresh');

    Http::assertSent(fn ($request): bool => ($request['grant_type'] ?? null) === 'refresh_token'
        && ($request['refresh_token'] ?? null) === 'old-refresh'
        && ($request['client_id'] ?? null) === 'client-123');
});

it('falls back to the resource origin when protected resource metadata is unavailable', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response('', 404),
        'https://mcp.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://mcp.test',
            'authorization_endpoint' => 'https://mcp.test/authorize',
            'token_endpoint' => 'https://mcp.test/token',
        ]),
    ]);

    $response = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect();

    expect($response->getTargetUrl())->toStartWith('https://mcp.test/authorize?');
});

it('throws when the token request fails', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    expect(fn (): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use')
        ->oAuth()
        ->refresh('bad-token'))
        ->toThrow(OAuthException::class, 'failed with status [400]');
});

it('throws when the authorization server metadata cannot be discovered', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response('', 500),
        'https://auth.test/.well-known/openid-configuration' => Http::response('', 500),
    ]);

    expect(fn (): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', clientSecret: 'secret')
        ->oAuth()
        ->clientCredentialsToken())
        ->toThrow(OAuthException::class, 'Unable to discover authorization server metadata');
});

it('throws when dynamic registration is needed but unsupported', function (): void {
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

    expect(fn (): RedirectResponse => Client::web('https://mcp.test/mcp')
        ->withOAuth(redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect())
        ->toThrow(OAuthException::class, 'does not support dynamic client registration');
});

it('throws when oauth is used without configuration', function (): void {
    expect(fn (): OAuthClient => Client::web('https://mcp.test/mcp')->oAuth())
        ->toThrow(OAuthException::class, 'No OAuth configuration');
});

it('requires a redirect uri before redirecting', function (): void {
    fakeDiscovery();

    expect(fn (): RedirectResponse => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->redirect())
        ->toThrow(OAuthException::class, 'redirect URI is required');
});

it('uses the server advertised resource metadata url when provided', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/custom-resource' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth('https://mcp.test/.well-known/custom-resource')
        ->redirect()
        ->getTargetUrl();

    expect($target)->toStartWith('https://auth.test/authorize?');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://mcp.test/.well-known/custom-resource');
});

it('rejects an authorization server whose issuer does not match', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://evil.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    expect(fn (): RedirectResponse => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect())
        ->toThrow(OAuthException::class, 'did not match the expected issuer');
});

it('rejects protected resource metadata whose resource does not match', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'resource' => 'https://other.test/mcp',
            'authorization_servers' => ['https://auth.test'],
        ]),
    ]);

    expect(fn (): RedirectResponse => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect())
        ->toThrow(OAuthException::class, 'did not match the expected resource');
});

it('rejects authorization server metadata that omits the issuer', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    expect(fn (): RedirectResponse => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect())
        ->toThrow(OAuthException::class, 'did not match the expected issuer');
});

it('rejects an authorization server that does not advertise the S256 PKCE method', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
            'code_challenge_methods_supported' => ['plain'],
        ]),
    ]);

    expect(fn (): RedirectResponse => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect())
        ->toThrow(OAuthException::class, 'does not support the required S256');
});

it('rejects an authorization server served over plain http', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['http://auth.test'],
        ]),
    ]);

    expect(fn (): RedirectResponse => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect())
        ->toThrow(OAuthException::class, 'must be served over HTTPS');
});

it('falls back to openid connect discovery when oauth metadata is absent', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response('', 404),
        'https://auth.test/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect()
        ->getTargetUrl();

    expect($target)->toStartWith('https://auth.test/authorize?');
});

it('validates a matching iss parameter on the authorization callback', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'access-token']),
    ]);

    $key = 'mcp.oauth.'.sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
        'issuer' => 'https://auth.test',
        'iss_supported' => true,
    ]);

    app()->instance('request', Request::create('https://app.test/callback', 'GET', [
        'code' => 'auth-code',
        'state' => 'the-state',
        'iss' => 'https://auth.test',
    ]));

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->callbackToken();

    expect($token->accessToken)->toBe('access-token');
});

it('rejects a mismatched iss parameter on the authorization callback', function (): void {
    fakeDiscovery();

    $key = 'mcp.oauth.'.sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
        'issuer' => 'https://auth.test',
        'iss_supported' => true,
    ]);

    app()->instance('request', Request::create('https://app.test/callback', 'GET', [
        'code' => 'auth-code',
        'state' => 'the-state',
        'iss' => 'https://evil.test',
    ]));

    expect(fn (): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->callbackToken())
        ->toThrow(OAuthException::class, 'iss) parameter did not match');
});

it('rejects a missing iss parameter when the server advertises support', function (): void {
    fakeDiscovery();

    $key = 'mcp.oauth.'.sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'client-123',
        'client_secret' => null,
        'token_endpoint' => 'https://auth.test/token',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
        'issuer' => 'https://auth.test',
        'iss_supported' => true,
    ]);

    app()->instance('request', Request::create('https://app.test/callback', 'GET', [
        'code' => 'auth-code',
        'state' => 'the-state',
    ]));

    expect(fn (): TokenSet => Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->callbackToken())
        ->toThrow(OAuthException::class, 'missing the required iss parameter');
});

it('records the issuer in the session for callback validation', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
            'authorization_response_iss_parameter_supported' => true,
        ]),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect();

    $stored = Session::get('mcp.oauth.'.sha1('https://mcp.test/mcp'));

    expect($stored['issuer'])->toBe('https://auth.test')
        ->and($stored['iss_supported'])->toBeTrue();
});

it('defaults the scope to mcp:use', function (): void {
    fakeDiscovery();

    $target = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect()
        ->getTargetUrl();

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('mcp:use');
});

it('falls back to scopes_supported from the protected resource metadata', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
            'scopes_supported' => ['mcp:read', 'mcp:write'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', scope: null, redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect()
        ->getTargetUrl();

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('mcp:read mcp:write');
});

it('prefers the challenge scope over scopes_supported', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
            'scopes_supported' => ['mcp:read'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
        ]),
    ]);

    $target = Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123', scope: null, redirectUri: 'https://app.test/callback')
        ->oAuth(challengeScope: 'files:read files:write')
        ->redirect()
        ->getTargetUrl();

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('files:read files:write');
});

it('sends a native application_type when registering a localhost client', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/register' => Http::response(['client_id' => 'dcr-1']),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(redirectUri: 'http://localhost:3000/callback')
        ->oAuth()
        ->redirect();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.test/register'
        && ($request['application_type'] ?? null) === 'native');
});

it('sends a web application_type when registering a remote client', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/register' => Http::response(['client_id' => 'dcr-1']),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.test/register'
        && ($request['application_type'] ?? null) === 'web');
});

it('normalizes the resource to its canonical form without a trailing slash', function (): void {
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

    $target = Client::web('https://mcp.test/mcp/#fragment')
        ->withOAuth(clientId: 'client-123', redirectUri: 'https://app.test/callback')
        ->oAuth()
        ->redirect()
        ->getTargetUrl();

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($query['resource'])->toBe('https://mcp.test/mcp');
});

it('includes the resource parameter on a refresh token request', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'fresh-token']),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'client-123')
        ->oAuth()
        ->refresh('old-refresh');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://auth.test/token'
        && ($request['grant_type'] ?? null) === 'refresh_token'
        && ($request['resource'] ?? null) === 'https://mcp.test/mcp');
});

it('defaults the redirect uri to the package callback route for a registered client', function (): void {
    fakeDiscovery();

    Route::get('mcp/oauth/github/callback', fn (): string => '')
        ->name('mcp.oauth.github.callback');

    $target = Client::web('https://mcp.test/mcp')
        ->setRegisteredName('github')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use')
        ->oAuth()
        ->redirect()
        ->getTargetUrl();

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe(url('mcp/oauth/github/callback'));
});

it('lets an explicit redirect uri override the default callback route', function (): void {
    fakeDiscovery();

    Route::get('mcp/oauth/github/callback', fn (): string => '')
        ->name('mcp.oauth.github.callback');

    $target = Client::web('https://mcp.test/mcp')
        ->setRegisteredName('github')
        ->withOAuth(clientId: 'client-123', scope: 'mcp:use', redirectUri: 'https://app.test/custom')
        ->oAuth()
        ->redirect()
        ->getTargetUrl();

    parse_str((string) parse_url($target, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('https://app.test/custom');
});

it('surfaces the dynamically registered credentials on the token set', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'access-token', 'refresh_token' => 'refresh-token']),
    ]);

    $key = 'mcp.oauth.'.sha1('https://mcp.test/mcp');

    Session::put($key, [
        'state' => 'the-state',
        'verifier' => 'the-verifier',
        'client_id' => 'dcr-999',
        'client_secret' => 'dcr-secret',
        'token_endpoint' => 'https://auth.test/token',
        'token_auth_method' => 'client_secret_post',
        'redirect_uri' => 'https://app.test/callback',
        'return_to' => null,
    ]);

    app()->instance('request', Request::create('https://app.test/callback', 'GET', [
        'code' => 'auth-code',
        'state' => 'the-state',
    ]));

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth()
        ->oAuth()
        ->callbackToken();

    expect($token->clientId)->toBe('dcr-999')
        ->and($token->clientSecret)->toBe('dcr-secret');
});

it('refreshes using explicitly passed client credentials', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'fresh-token']),
    ]);

    $token = Client::web('https://mcp.test/mcp')
        ->withOAuth(scope: 'mcp:use')
        ->oAuth()
        ->refresh('old-refresh', clientId: 'dcr-999', clientSecret: 'dcr-secret');

    expect($token->clientId)->toBe('dcr-999');

    Http::assertSent(fn ($request): bool => ($request['grant_type'] ?? null) === 'refresh_token'
        && ($request['client_id'] ?? null) === 'dcr-999'
        && ($request['client_secret'] ?? null) === 'dcr-secret');
});

it('uses client_secret_basic when the server only supports it', function (): void {
    Http::fake([
        'https://mcp.test/.well-known/oauth-protected-resource/mcp' => Http::response([
            'authorization_servers' => ['https://auth.test'],
        ]),
        'https://auth.test/.well-known/oauth-authorization-server' => Http::response([
            'issuer' => 'https://auth.test',
            'authorization_endpoint' => 'https://auth.test/authorize',
            'token_endpoint' => 'https://auth.test/token',
            'token_endpoint_auth_methods_supported' => ['client_secret_basic'],
        ]),
        'https://auth.test/token' => Http::response(['access_token' => 'machine-token']),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'svc', clientSecret: 'secret', scope: 'mcp:use')
        ->oAuth()
        ->clientCredentialsToken();

    Http::assertSent(function ($request): bool {
        $authorization = $request->header('Authorization')[0] ?? '';

        return $request->url() === 'https://auth.test/token'
            && $authorization === 'Basic '.base64_encode('svc:secret')
            && ! array_key_exists('client_secret', $request->data());
    });
});

it('honors an explicitly configured token endpoint auth method', function (): void {
    fakeDiscovery();

    Http::fake([
        'https://auth.test/token' => Http::response(['access_token' => 'machine-token']),
    ]);

    Client::web('https://mcp.test/mcp')
        ->withOAuth(clientId: 'svc', clientSecret: 'secret', scope: 'mcp:use', tokenEndpointAuthMethod: 'client_secret_basic')
        ->oAuth()
        ->clientCredentialsToken();

    Http::assertSent(fn ($request): bool => ($request->header('Authorization')[0] ?? '') === 'Basic '.base64_encode('svc:secret'));
});
