<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Encryption\Encrypter as ConcreteEncrypter;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Auth\ClientRegistration;
use Laravel\Mcp\Client\Auth\EncryptedCacheStore;
use Laravel\Mcp\Client\Auth\OAuthHandler;
use Laravel\Mcp\Client\Auth\TokenSet;
use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;
use Laravel\Mcp\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Exceptions\OAuthException;
use Laravel\Mcp\Exceptions\PkceUnsupportedException;

function fakeDiscovery(bool $withPkce = false, bool $withAuthorizationEndpoint = false): void
{
    $asPayload = [
        'issuer' => 'https://auth.example.com',
        'token_endpoint' => 'https://auth.example.com/token',
        'grant_types_supported' => ['client_credentials', 'authorization_code', 'refresh_token'],
    ];

    if ($withPkce) {
        $asPayload['code_challenge_methods_supported'] = ['S256'];
    }

    if ($withAuthorizationEndpoint) {
        $asPayload['authorization_endpoint'] = 'https://auth.example.com/authorize';
    }

    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource*' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
            'scopes_supported' => ['mcp:read'],
        ]), 200, ['Content-Type' => 'application/json']),
        'https://auth.example.com/.well-known/oauth-authorization-server*' => Http::response(json_encode($asPayload), 200, ['Content-Type' => 'application/json']),
    ]);
}

function makeHandlerStore(): EncryptedCacheStore
{
    return new EncryptedCacheStore(
        cache: new CacheRepository(new ArrayStore(serializesValues: true)),
        crypt: new ConcreteEncrypter(random_bytes(32), 'AES-256-CBC'),
    );
}

function seedToken(EncryptedCacheStore $store, string $key, TokenSet $token): void
{
    $store->put($key, $token->toArray());
}

function readToken(EncryptedCacheStore $store, string $key): ?TokenSet
{
    $data = $store->get($key);

    return $data === null ? null : TokenSet::fromArray($data);
}

/**
 * @param  array<int, PsrResponse>  $responses
 * @param  array<int, array{request: RequestInterface, response: PsrResponse}>  $history
 */
function makeGuzzleClient(array $responses, array &$history = []): GuzzleClient
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    return new GuzzleClient(['handler' => $stack]);
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

    $history = [];
    $guzzle = makeGuzzleClient([tokenResponse('access-one')], $history);
    $store = makeHandlerStore();

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('access-one')
        ->and(readToken($store, 'mcp-auth:notion'))->not->toBeNull()
        ->and(readToken($store, 'mcp-auth:notion')?->accessToken)->toBe('access-one');

    expect($history)->toHaveCount(1);
    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body)->toHaveKey('grant_type', 'client_credentials')
        ->and($body)->toHaveKey('resource', 'https://mcp.example.com/mcp')
        ->and($body)->toHaveKey('scope', 'mcp:read');
});

it('returns the cached token without re-running discovery or the grant', function (): void {
    fakeDiscovery();

    $history = [];
    $guzzle = makeGuzzleClient([], $history);
    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('cached-one', null, time() + 600, 'mcp:read'));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('cached-one')
        ->and($history)->toHaveCount(0);
    Http::assertNothingSent();
});

it('uses the refresh_token grant when the cached token has a refresh token', function (): void {
    fakeDiscovery();

    $history = [];
    $guzzle = makeGuzzleClient([tokenResponse('refreshed', 'refresh-2', 3600)], $history);
    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('stale', 'refresh-1', time() - 60, null));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('refreshed')
        ->and(readToken($store, 'mcp-auth:notion')?->refreshToken)->toBe('refresh-2');

    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body)->toHaveKey('grant_type', 'refresh_token')
        ->and($body)->toHaveKey('refresh_token', 'refresh-1');
});

it('falls back to client_credentials when refresh fails', function (): void {
    fakeDiscovery();

    $history = [];
    $guzzle = makeGuzzleClient([
        new PsrResponse(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_grant'])),
        tokenResponse('new-access'),
    ], $history);
    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('stale', 'refresh-1', time() - 60, null));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('new-access');
    expect($history)->toHaveCount(2);
    parse_str((string) $history[1]['request']->getBody(), $second);
    expect($second['grant_type'])->toBe('client_credentials');
});

it('re-grants with client_credentials when no refresh token is available', function (): void {
    fakeDiscovery();

    $history = [];
    $guzzle = makeGuzzleClient([tokenResponse('fresh-access')], $history);
    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('stale', null, time() - 60, null));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('fresh-access');
    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body['grant_type'])->toBe('client_credentials');
});

it('replays the grant with the scope from a WWW-Authenticate challenge', function (): void {
    fakeDiscovery();

    $history = [];
    $guzzle = makeGuzzleClient([tokenResponse('upgraded')], $history);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: 'mcp:read',
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    $challenge = WwwAuthenticateChallenge::parse('Bearer error="insufficient_scope", scope="mcp:read mcp:write"');

    expect($handler->bearerTokenAfterChallenge($challenge))->toBe('upgraded');
    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body['scope'])->toBe('mcp:read mcp:write');
});

it('caps a challenge replay at a single retry per handler instance', function (): void {
    fakeDiscovery();

    $guzzle = makeGuzzleClient([tokenResponse('once')]);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    $challenge = WwwAuthenticateChallenge::parse('Bearer scope="mcp:read"');

    $handler->bearerTokenAfterChallenge($challenge);

    expect(fn (): string => $handler->bearerTokenAfterChallenge($challenge))
        ->toThrow(OAuthException::class, 'already retried');
});

it('omits the scope parameter when no scope is configured or advertised', function (): void {
    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource*' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ]), 200, ['Content-Type' => 'application/json']),
        'https://auth.example.com/.well-known/oauth-authorization-server*' => Http::response(json_encode([
            'issuer' => 'https://auth.example.com',
            'token_endpoint' => 'https://auth.example.com/token',
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    $history = [];
    $guzzle = makeGuzzleClient([tokenResponse('no-scope')], $history);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('no-scope');
    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body)->not->toHaveKey('scope');
});

it('returns null from bearerTokenIfCached when no token is stored', function (): void {
    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
    );

    expect($handler->bearerTokenIfCached())->toBeNull();
    Http::assertNothingSent();
});

it('returns the cached token from bearerTokenIfCached without touching the network', function (): void {
    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('warm', null, time() + 600, null));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
    );

    expect($handler->bearerTokenIfCached())->toBe('warm');
});

it('forgets the cached token entry', function (): void {
    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('warm', null, time() + 600, null));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
    );

    $handler->forget();

    expect(readToken($store, 'mcp-auth:notion'))->toBeNull();
});

it('wraps an identity provider failure in an OAuthException', function (): void {
    fakeDiscovery();

    $guzzle = makeGuzzleClient([
        new PsrResponse(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client'])),
    ]);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'wrong',
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect(fn (): string => $handler->bearerToken())
        ->toThrow(OAuthException::class, 'failed the client_credentials grant');
});

it('uses the inline storage key when no registered name is set', function (): void {
    fakeDiscovery();

    $guzzle = makeGuzzleClient([tokenResponse('inline-access')]);
    $store = makeHandlerStore();

    $handler = new OAuthHandler(
        serverName: null,
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: 'mcp:read',
        store: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    $handler->bearerToken();

    expect(readToken($store, 'mcp-auth:inline'))->not->toBeNull();
});

it('uses a user-scoped storage key when a user key is set', function (): void {
    fakeDiscovery();

    $guzzle = makeGuzzleClient([tokenResponse('per-user')]);
    $store = makeHandlerStore();

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: 'mcp:read',
        store: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
        userKey: '42',
    );

    $handler->bearerToken();

    expect(readToken($store, 'mcp-auth:notion:user:42'))->not->toBeNull()
        ->and(readToken($store, 'mcp-auth:notion'))->toBeNull();
});

it('reports needsAuthorization() true for an authorization_code client without a cached token', function (): void {
    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: null,
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
    );

    expect($handler->needsAuthorization())->toBeTrue()
        ->and($handler->requiresUserConsent())->toBeTrue();
});

it('reports needsAuthorization() false once a token is cached even if expired', function (): void {
    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('stale', 'refresh-1', time() - 60, null));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: null,
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
    );

    expect($handler->needsAuthorization())->toBeFalse();
});

it('reports needsAuthorization() false for client_credentials clients', function (): void {
    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
    );

    expect($handler->needsAuthorization())->toBeFalse();
});

it('builds an authorization URL with PKCE, state, and the resource parameter', function (): void {
    fakeDiscovery(withPkce: true, withAuthorizationEndpoint: true);

    $store = makeHandlerStore();

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: 'mcp:read mcp:write',
        store: $store,
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
    );

    $redirect = $handler->startAuthorization(intendedUrl: '/dashboard');

    expect($redirect->url)->toStartWith('https://auth.example.com/authorize?');
    parse_str((string) parse_url($redirect->url, PHP_URL_QUERY), $query);
    expect($query)->toHaveKey('response_type', 'code')
        ->and($query)->toHaveKey('client_id', 'cid-123')
        ->and($query)->toHaveKey('code_challenge_method', 'S256')
        ->and($query)->toHaveKey('resource', 'https://mcp.example.com/mcp')
        ->and($query)->toHaveKey('scope', 'mcp:read mcp:write')
        ->and($query['state'])->toBe($redirect->state);

    $payload = $store->pull('mcp-oauth-state:'.$redirect->state);
    expect($payload)->not->toBeNull()
        ->and($payload['intended_url'])->toBe('/dashboard')
        ->and($payload['pkce_verifier'])->toBeString();
});

it('refuses to start authorization_code when the AS does not advertise S256 PKCE', function (): void {
    fakeDiscovery(withPkce: false, withAuthorizationEndpoint: true);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
    );

    expect(fn (): mixed => $handler->startAuthorization())
        ->toThrow(PkceUnsupportedException::class);
});

it('completes the authorization_code flow by exchanging the code for a token', function (): void {
    fakeDiscovery(withPkce: true, withAuthorizationEndpoint: true);

    $history = [];
    $guzzle = makeGuzzleClient([tokenResponse('user-access', 'user-refresh')], $history);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: 'mcp:read',
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
    );

    $redirect = $handler->startAuthorization();
    $token = $handler->completeAuthorization('auth-code-789', $redirect->state);

    expect($token->accessToken)->toBe('user-access')
        ->and($token->refreshToken)->toBe('user-refresh');

    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body)->toHaveKey('grant_type', 'authorization_code')
        ->and($body)->toHaveKey('code', 'auth-code-789')
        ->and($body)->toHaveKey('code_verifier')
        ->and($body)->toHaveKey('resource', 'https://mcp.example.com/mcp')
        ->and($body)->toHaveKey('redirect_uri', 'https://app.example.com/mcp/notion/callback');
});

it('rejects a callback whose state was issued for a different user', function (): void {
    fakeDiscovery(withPkce: true, withAuthorizationEndpoint: true);

    $store = makeHandlerStore();

    $starter = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
        userKey: '1',
    );

    $redirect = $starter->startAuthorization();

    $completer = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
        userKey: '2',
    );

    expect(fn (): mixed => $completer->completeAuthorization('code', $redirect->state))
        ->toThrow(OAuthException::class, 'does not belong to the current user');
});

it('rejects an unknown or expired state when completing authorization', function (): void {
    fakeDiscovery(withPkce: true, withAuthorizationEndpoint: true);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
    );

    expect(fn (): mixed => $handler->completeAuthorization('code', 'never-issued'))
        ->toThrow(OAuthException::class, 'invalid or expired');
});

it('throws AuthorizationRequiredException with a populated URL when no token is cached', function (): void {
    fakeDiscovery(withPkce: true, withAuthorizationEndpoint: true);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
    );

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
    fakeDiscovery(withPkce: true, withAuthorizationEndpoint: true);

    $history = [];
    $guzzle = makeGuzzleClient([tokenResponse('rotated', 'rotated-refresh')], $history);

    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('stale', 'previous-refresh', time() - 60, 'mcp:read'));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: 'mcp:read',
        store: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
    );

    expect($handler->bearerToken())->toBe('rotated');

    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body['grant_type'])->toBe('refresh_token')
        ->and($body['refresh_token'])->toBe('previous-refresh');
});

it('throws AuthorizationRequiredException when an expired auth_code token has no refresh token', function (): void {
    fakeDiscovery(withPkce: true, withAuthorizationEndpoint: true);

    $store = makeHandlerStore();
    seedToken($store, 'mcp-auth:notion', new TokenSet('stale', null, time() - 60, null));

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'cid-123',
        clientSecret: null,
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/notion/callback',
    );

    expect(fn (): string => $handler->bearerToken())
        ->toThrow(AuthorizationRequiredException::class);
});

it('canonicalizes the resource parameter (lowercase scheme/host, no trailing slash)', function (): void {
    fakeDiscovery();

    $history = [];
    $guzzle = makeGuzzleClient([tokenResponse('canon')], $history);

    $handler = new OAuthHandler(
        serverName: 'notion',
        mcpUrl: 'HTTPS://MCP.EXAMPLE.COM/mcp/',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    $handler->bearerToken();

    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body['resource'])->toBe('https://mcp.example.com/mcp');
});

it('dynamically registers when withOauth() is called without a client_id', function (): void {
    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource*' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ]), 200, ['Content-Type' => 'application/json']),
        'https://auth.example.com/.well-known/oauth-authorization-server*' => Http::response(json_encode([
            'issuer' => 'https://auth.example.com',
            'token_endpoint' => 'https://auth.example.com/token',
            'authorization_endpoint' => 'https://auth.example.com/authorize',
            'code_challenge_methods_supported' => ['S256'],
            'registration_endpoint' => 'https://auth.example.com/register',
        ]), 200, ['Content-Type' => 'application/json']),
        'https://auth.example.com/register' => Http::response(json_encode([
            'client_id' => 'cid-dyn',
        ]), 201, ['Content-Type' => 'application/json']),
    ]);

    $store = makeHandlerStore();

    $handler = new OAuthHandler(
        serverName: 'nightwatch',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: null,
        clientSecret: null,
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/nightwatch/callback',
    );

    $redirect = $handler->startAuthorization();

    parse_str((string) parse_url($redirect->url, PHP_URL_QUERY), $query);
    expect($query['client_id'])->toBe('cid-dyn');

    $registration = $store->get('mcp-client:nightwatch');
    expect($registration)->not->toBeNull()
        ->and(ClientRegistration::fromArray($registration)->clientId)->toBe('cid-dyn');

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
    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource*' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ]), 200, ['Content-Type' => 'application/json']),
        'https://auth.example.com/.well-known/oauth-authorization-server*' => Http::response(json_encode([
            'issuer' => 'https://auth.example.com',
            'token_endpoint' => 'https://auth.example.com/token',
            'authorization_endpoint' => 'https://auth.example.com/authorize',
            'code_challenge_methods_supported' => ['S256'],
            'registration_endpoint' => 'https://auth.example.com/register',
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    $store = makeHandlerStore();
    $store->put('mcp-client:nightwatch', (new ClientRegistration('cid-cached'))->toArray());

    $handler = new OAuthHandler(
        serverName: 'nightwatch',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: null,
        clientSecret: null,
        configuredScope: null,
        store: $store,
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/mcp/nightwatch/callback',
    );

    $redirect = $handler->startAuthorization();
    parse_str((string) parse_url($redirect->url, PHP_URL_QUERY), $query);

    expect($query['client_id'])->toBe('cid-cached');

    Http::assertNotSent(fn ($request): bool => (string) $request->url() === 'https://auth.example.com/register');
});

it('throws OAuthException when DCR is requested but the AS does not advertise registration_endpoint', function (): void {
    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource*' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ]), 200, ['Content-Type' => 'application/json']),
        'https://auth.example.com/.well-known/oauth-authorization-server*' => Http::response(json_encode([
            'issuer' => 'https://auth.example.com',
            'token_endpoint' => 'https://auth.example.com/token',
            'authorization_endpoint' => 'https://auth.example.com/authorize',
            'code_challenge_methods_supported' => ['S256'],
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    $handler = new OAuthHandler(
        serverName: 'nightwatch',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: null,
        clientSecret: null,
        configuredScope: null,
        store: makeHandlerStore(),
        discovery: new AuthServerDiscovery,
        redirectUriResolver: fn (): string => 'https://app.example.com/cb',
    );

    expect(fn (): mixed => $handler->startAuthorization())
        ->toThrow(OAuthException::class, 'does not advertise a registration_endpoint');
});
