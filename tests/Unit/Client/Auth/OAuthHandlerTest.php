<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Contracts\Cache\Repository as RepositoryContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Auth\ClientRegistration;
use Laravel\Mcp\Client\Auth\EncryptedCacheStore;
use Laravel\Mcp\Client\Auth\TokenSet;
use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;
use Laravel\Mcp\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Exceptions\OAuthException;
use Laravel\Mcp\Exceptions\PkceUnsupportedException;
use Tests\Fixtures\Client\Auth\InMemoryTokenStore;
use Tests\Fixtures\Client\Auth\OAuthHandlerBuilder;

function oauth(): OAuthHandlerBuilder
{
    return new OAuthHandlerBuilder;
}

/**
 * Fake the protected-resource and authorization-server discovery documents.
 *
 * @param  list<string>  $scopesSupported
 * @param  array<string, mixed>  $extra  additional Http::fake stubs (e.g. a DCR endpoint)
 */
function fakeDiscovery(
    bool $pkce = false,
    bool $authorizationEndpoint = false,
    bool $registration = false,
    array $scopesSupported = ['mcp:read'],
    array $extra = [],
): void {
    $protectedResource = ['resource' => 'https://mcp.example.com/mcp', 'authorization_servers' => ['https://auth.example.com']];

    if ($scopesSupported !== []) {
        $protectedResource['scopes_supported'] = $scopesSupported;
    }

    $authServer = [
        'issuer' => 'https://auth.example.com',
        'token_endpoint' => 'https://auth.example.com/token',
        'grant_types_supported' => ['client_credentials', 'authorization_code', 'refresh_token'],
    ];

    if ($pkce) {
        $authServer['code_challenge_methods_supported'] = ['S256'];
    }

    if ($authorizationEndpoint) {
        $authServer['authorization_endpoint'] = 'https://auth.example.com/authorize';
    }

    if ($registration) {
        $authServer['registration_endpoint'] = 'https://auth.example.com/register';
    }

    Http::fake(array_merge([
        'https://mcp.example.com/.well-known/oauth-protected-resource*' => Http::response(json_encode($protectedResource), 200, ['Content-Type' => 'application/json']),
        'https://auth.example.com/.well-known/oauth-authorization-server*' => Http::response(json_encode($authServer), 200, ['Content-Type' => 'application/json']),
    ], $extra));
}

function tokenResponse(string $access, ?string $refresh = null, int $expiresIn = 3600, ?string $scope = null): PsrResponse
{
    $body = ['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => $expiresIn];

    if ($refresh !== null) {
        $body['refresh_token'] = $refresh;
    }

    if ($scope !== null) {
        $body['scope'] = $scope;
    }

    return new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode($body));
}

it('runs discovery and writes a token to the store on the cold path', function (): void {
    fakeDiscovery();

    $oauth = oauth()->grants(tokenResponse('access-one'));

    expect($oauth->handler()->bearerToken())->toBe('access-one')
        ->and($oauth->storedToken()?->accessToken)->toBe('access-one')
        ->and($oauth->grantCount())->toBe(1)
        ->and($oauth->grantBody())->toMatchArray([
            'grant_type' => 'client_credentials',
            'resource' => 'https://mcp.example.com/mcp',
            'scope' => 'mcp:read',
        ]);
});

it('persists tokens through a custom TokenStore implementation', function (): void {
    fakeDiscovery();

    $store = new InMemoryTokenStore;

    $oauth = oauth()->usingStore($store)->grants(tokenResponse('access-custom'));

    expect($oauth->handler()->bearerToken())->toBe('access-custom')
        ->and($store->entries)->toHaveKey('mcp-auth:notion')
        ->and(TokenSet::fromArray($store->entries['mcp-auth:notion'])->accessToken)->toBe('access-custom');
});

it('captures a refresh-token expiry from refresh_token_expires_in', function (): void {
    fakeDiscovery();

    $oauth = oauth()->grants(new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode([
        'access_token' => 'access-x',
        'token_type' => 'Bearer',
        'expires_in' => 60,
        'refresh_token' => 'refresh-x',
        'refresh_token_expires_in' => 120,
    ])));

    expect($oauth->handler()->bearerToken())->toBe('access-x');

    $stored = $oauth->storedToken();

    expect($stored?->refreshExpiresAt)->not->toBeNull()
        ->and($stored->refreshExpiresAt)->toBeGreaterThan(time());
});

it('returns the cached token without re-running discovery or the grant', function (): void {
    fakeDiscovery();

    $oauth = oauth()->seed(new TokenSet('cached-one', null, time() + 600, 'mcp:read'));

    expect($oauth->handler()->bearerToken())->toBe('cached-one')
        ->and($oauth->grantCount())->toBe(0);
    Http::assertNothingSent();
});

it('uses the refresh_token grant when the cached token has a refresh token', function (): void {
    fakeDiscovery();

    $oauth = oauth()
        ->seed(new TokenSet('stale', 'refresh-1', time() - 60, null))
        ->grants(tokenResponse('refreshed', 'refresh-2'));

    expect($oauth->handler()->bearerToken())->toBe('refreshed')
        ->and($oauth->storedToken()?->refreshToken)->toBe('refresh-2')
        ->and($oauth->grantBody())->toMatchArray([
            'grant_type' => 'refresh_token',
            'refresh_token' => 'refresh-1',
        ]);
});

it('falls back to client_credentials when refresh fails', function (): void {
    fakeDiscovery();

    $oauth = oauth()
        ->seed(new TokenSet('stale', 'refresh-1', time() - 60, null))
        ->grants(
            new PsrResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_grant'])),
            tokenResponse('new-access'),
        );

    expect($oauth->handler()->bearerToken())->toBe('new-access')
        ->and($oauth->grantCount())->toBe(2)
        ->and($oauth->grantBody(1)['grant_type'])->toBe('client_credentials');
});

it('re-grants with client_credentials when no refresh token is available', function (): void {
    fakeDiscovery();

    $oauth = oauth()
        ->seed(new TokenSet('stale', null, time() - 60, null))
        ->grants(tokenResponse('fresh-access'));

    expect($oauth->handler()->bearerToken())->toBe('fresh-access')
        ->and($oauth->grantBody()['grant_type'])->toBe('client_credentials');
});

it('writes refresh-bearing tokens with a long ttl so silent refresh survives access-token expiry', function (): void {
    fakeDiscovery();

    $captured = null;
    $repo = Mockery::mock(RepositoryContract::class);
    $repo->shouldReceive('getStore')->andReturnNull();
    $repo->shouldReceive('get')->andReturnNull();
    $repo->shouldReceive('put')->andReturnUsing(function ($key, $value, $ttl) use (&$captured): bool {
        $captured = $ttl;

        return true;
    });

    oauth()
        ->usingStore(new EncryptedCacheStore($repo, new Encrypter(random_bytes(32), 'AES-256-CBC')))
        ->grants(tokenResponse('access', 'refresh-1'))
        ->handler()
        ->bearerToken();

    expect($captured)->toBeGreaterThan(86_400);
});

it('replays the grant with the scope from a WWW-Authenticate challenge', function (): void {
    fakeDiscovery();

    $oauth = oauth()
        ->with(['configuredScope' => 'mcp:read'])
        ->grants(tokenResponse('upgraded'));

    $challenge = WwwAuthenticateChallenge::parse('Bearer error="insufficient_scope", scope="mcp:read mcp:write"');

    expect($oauth->handler()->bearerTokenAfterChallenge($challenge))->toBe('upgraded')
        ->and($oauth->grantBody()['scope'])->toBe('mcp:read mcp:write');
});

it('caps a challenge replay at a single retry per handler instance', function (): void {
    fakeDiscovery();

    $handler = oauth()->grants(tokenResponse('once'))->handler();
    $challenge = WwwAuthenticateChallenge::parse('Bearer scope="mcp:read"');

    $handler->bearerTokenAfterChallenge($challenge);

    expect(fn (): string => $handler->bearerTokenAfterChallenge($challenge))
        ->toThrow(OAuthException::class, 'already retried');
});

it('omits the scope parameter when no scope is configured or advertised', function (): void {
    fakeDiscovery(scopesSupported: []);

    $oauth = oauth()->grants(tokenResponse('no-scope'));

    expect($oauth->handler()->bearerToken())->toBe('no-scope')
        ->and($oauth->grantBody())->not->toHaveKey('scope');
});

it('returns null from bearerTokenIfCached when no token is stored', function (): void {
    expect(oauth()->handler()->bearerTokenIfCached())->toBeNull();
    Http::assertNothingSent();
});

it('returns the cached token from bearerTokenIfCached without touching the network', function (): void {
    $handler = oauth()->seed(new TokenSet('warm', null, time() + 600, null))->handler();

    expect($handler->bearerTokenIfCached())->toBe('warm');
});

it('forgets the cached token entry', function (): void {
    $oauth = oauth()->seed(new TokenSet('warm', null, time() + 600, null));

    $oauth->handler()->forget();

    expect($oauth->storedToken())->toBeNull();
});

it('wraps an identity provider failure in an OAuthException', function (): void {
    fakeDiscovery();

    $handler = oauth()
        ->with(['clientSecret' => 'wrong'])
        ->grants(new PsrResponse(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client'])))
        ->handler();

    expect(fn (): string => $handler->bearerToken())
        ->toThrow(OAuthException::class, 'failed the client_credentials grant');
});

it('uses the inline storage key when no registered name is set', function (): void {
    fakeDiscovery();

    $oauth = oauth()
        ->with(['serverName' => null, 'configuredScope' => 'mcp:read'])
        ->grants(tokenResponse('inline-access'));

    $oauth->handler()->bearerToken();

    expect($oauth->storedToken('mcp-auth:inline'))->not->toBeNull();
});

it('uses a user-scoped storage key when a user key is set', function (): void {
    fakeDiscovery();

    $oauth = oauth()
        ->with(['configuredScope' => 'mcp:read', 'userKey' => '42'])
        ->grants(tokenResponse('per-user'));

    $oauth->handler()->bearerToken();

    expect($oauth->storedToken('mcp-auth:notion:user:42'))->not->toBeNull()
        ->and($oauth->storedToken('mcp-auth:notion'))->toBeNull();
});

it('reports needsAuthorization() true for an authorization_code client without a cached token', function (): void {
    $handler = oauth()->with(['clientSecret' => null])->handler();

    expect($handler->needsAuthorization())->toBeTrue()
        ->and($handler->requiresUserConsent())->toBeTrue();
});

it('reports needsAuthorization() false once a token is cached even if expired', function (): void {
    $handler = oauth()
        ->with(['clientSecret' => null])
        ->seed(new TokenSet('stale', 'refresh-1', time() - 60, null))
        ->handler();

    expect($handler->needsAuthorization())->toBeFalse();
});

it('reports needsAuthorization() false for client_credentials clients', function (): void {
    expect(oauth()->handler()->needsAuthorization())->toBeFalse();
});

it('builds an authorization URL with PKCE, state, and the resource parameter', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true);

    $oauth = oauth()->with([
        'clientId' => 'cid-123',
        'clientSecret' => null,
        'configuredScope' => 'mcp:read mcp:write',
        'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/notion/callback',
    ]);

    $redirect = $oauth->handler()->startAuthorization(intendedUrl: '/dashboard');

    expect($redirect->url)->toStartWith('https://auth.example.com/authorize?');
    parse_str((string) parse_url($redirect->url, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray([
        'response_type' => 'code',
        'client_id' => 'cid-123',
        'code_challenge_method' => 'S256',
        'resource' => 'https://mcp.example.com/mcp',
        'scope' => 'mcp:read mcp:write',
        'state' => $redirect->state,
    ]);

    $payload = $oauth->store->pull('mcp-oauth-state:'.$redirect->state);
    expect($payload)->not->toBeNull()
        ->and($payload['intended_url'])->toBe('/dashboard')
        ->and($payload['pkce_verifier'])->toBeString();
});

it('refuses to start authorization_code when the AS does not advertise S256 PKCE', function (): void {
    fakeDiscovery(authorizationEndpoint: true);

    $handler = oauth()->with([
        'clientId' => 'cid-123',
        'clientSecret' => null,
        'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/notion/callback',
    ])->handler();

    expect(fn (): mixed => $handler->startAuthorization())
        ->toThrow(PkceUnsupportedException::class);
});

it('completes the authorization_code flow by exchanging the code for a token', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true);

    $oauth = oauth()
        ->with([
            'clientId' => 'cid-123',
            'clientSecret' => null,
            'configuredScope' => 'mcp:read',
            'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/notion/callback',
        ])
        ->grants(tokenResponse('user-access', 'user-refresh'));

    $handler = $oauth->handler();
    $redirect = $handler->startAuthorization();
    $token = $handler->completeAuthorization('auth-code-789', $redirect->state);

    expect($token->accessToken)->toBe('user-access')
        ->and($token->refreshToken)->toBe('user-refresh')
        ->and($oauth->grantBody())->toMatchArray([
            'grant_type' => 'authorization_code',
            'code' => 'auth-code-789',
            'resource' => 'https://mcp.example.com/mcp',
            'redirect_uri' => 'https://app.example.com/mcp/notion/callback',
        ])
        ->and($oauth->grantBody())->toHaveKey('code_verifier');
});

it('rejects a callback whose state was issued for a different user', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true);

    $resolver = fn (): string => 'https://app.example.com/mcp/notion/callback';
    $authCodeClient = ['clientId' => 'cid-123', 'clientSecret' => null, 'redirectUriResolver' => $resolver];

    $starter = oauth()->with($authCodeClient + ['userKey' => '1']);
    $redirect = $starter->handler()->startAuthorization();

    $completer = oauth()->usingStore($starter->store)->with($authCodeClient + ['userKey' => '2'])->handler();

    expect(fn (): mixed => $completer->completeAuthorization('code', $redirect->state))
        ->toThrow(OAuthException::class, 'does not belong to the current user');
});

it('rejects an unknown or expired state when completing authorization', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true);

    $handler = oauth()->with([
        'clientId' => 'cid-123',
        'clientSecret' => null,
        'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/notion/callback',
    ])->handler();

    expect(fn (): mixed => $handler->completeAuthorization('code', 'never-issued'))
        ->toThrow(OAuthException::class, 'invalid or expired');
});

it('throws AuthorizationRequiredException with a populated URL when no token is cached', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true);

    $handler = oauth()->with([
        'clientId' => 'cid-123',
        'clientSecret' => null,
        'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/notion/callback',
    ])->handler();

    try {
        $handler->bearerToken();
        $this->fail('Expected AuthorizationRequiredException');
    } catch (AuthorizationRequiredException $authorizationRequiredException) {
        expect($authorizationRequiredException->serverName)->toBe('notion')
            ->and($authorizationRequiredException->authorizationUrl)->toStartWith('https://auth.example.com/authorize?')
            ->and($authorizationRequiredException->state)->toBeString();
    }
});

it('uses a refresh_token grant on an expired authorization_code token', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true);

    $oauth = oauth()
        ->with([
            'clientId' => 'cid-123',
            'clientSecret' => null,
            'configuredScope' => 'mcp:read',
            'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/notion/callback',
        ])
        ->seed(new TokenSet('stale', 'previous-refresh', time() - 60, 'mcp:read'))
        ->grants(tokenResponse('rotated', 'rotated-refresh'));

    expect($oauth->handler()->bearerToken())->toBe('rotated')
        ->and($oauth->grantBody())->toMatchArray([
            'grant_type' => 'refresh_token',
            'refresh_token' => 'previous-refresh',
        ]);
});

it('throws AuthorizationRequiredException when an expired auth_code token has no refresh token', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true);

    $handler = oauth()
        ->with([
            'clientId' => 'cid-123',
            'clientSecret' => null,
            'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/notion/callback',
        ])
        ->seed(new TokenSet('stale', null, time() - 60, null))
        ->handler();

    expect(fn (): string => $handler->bearerToken())
        ->toThrow(AuthorizationRequiredException::class);
});

it('canonicalizes the resource parameter (lowercase scheme/host, no trailing slash)', function (): void {
    fakeDiscovery();

    $oauth = oauth()
        ->with(['mcpUrl' => 'HTTPS://MCP.EXAMPLE.COM/mcp/'])
        ->grants(tokenResponse('canon'));

    $oauth->handler()->bearerToken();

    expect($oauth->grantBody()['resource'])->toBe('https://mcp.example.com/mcp');
});

it('dynamically registers when withOauth() is called without a client_id', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true, registration: true, scopesSupported: [], extra: [
        'https://auth.example.com/register' => Http::response(json_encode(['client_id' => 'cid-dyn']), 201, ['Content-Type' => 'application/json']),
    ]);

    $oauth = oauth()->with([
        'serverName' => 'nightwatch',
        'clientId' => null,
        'clientSecret' => null,
        'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/nightwatch/callback',
    ]);

    $redirect = $oauth->handler()->startAuthorization();

    parse_str((string) parse_url($redirect->url, PHP_URL_QUERY), $query);
    expect($query['client_id'])->toBe('cid-dyn')
        ->and(ClientRegistration::fromArray($oauth->store->get('mcp-client:nightwatch'))->clientId)->toBe('cid-dyn');

    Http::assertSent(function ($request): bool {
        if ((string) $request->url() !== 'https://auth.example.com/register') {
            return false;
        }

        $body = json_decode((string) $request->body(), true);

        return $body['token_endpoint_auth_method'] === 'none'
            && $body['redirect_uris'] === ['https://app.example.com/mcp/nightwatch/callback'];
    });
});

it('reuses a cached registration without re-registering', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true, registration: true, scopesSupported: []);

    $oauth = oauth()->with([
        'serverName' => 'nightwatch',
        'clientId' => null,
        'clientSecret' => null,
        'redirectUriResolver' => fn (): string => 'https://app.example.com/mcp/nightwatch/callback',
    ]);
    $oauth->store->put('mcp-client:nightwatch', (new ClientRegistration('cid-cached'))->toArray());

    $redirect = $oauth->handler()->startAuthorization();
    parse_str((string) parse_url($redirect->url, PHP_URL_QUERY), $query);

    expect($query['client_id'])->toBe('cid-cached');
    Http::assertNotSent(fn ($request): bool => (string) $request->url() === 'https://auth.example.com/register');
});

it('throws OAuthException when DCR is requested but the AS does not advertise registration_endpoint', function (): void {
    fakeDiscovery(pkce: true, authorizationEndpoint: true, scopesSupported: []);

    $handler = oauth()->with([
        'serverName' => 'nightwatch',
        'clientId' => null,
        'clientSecret' => null,
        'redirectUriResolver' => fn (): string => 'https://app.example.com/cb',
    ])->handler();

    expect(fn (): mixed => $handler->startAuthorization())
        ->toThrow(OAuthException::class, 'does not advertise a registration_endpoint');
});
