<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\Auth\AuthServerDiscovery;
use Laravel\Mcp\Client\Auth\InMemoryTokenStore;
use Laravel\Mcp\Client\Auth\OAuthHandler;
use Laravel\Mcp\Client\Auth\TokenSet;
use Laravel\Mcp\Client\Auth\WwwAuthenticateChallenge;
use Laravel\Mcp\Exceptions\OAuthException;

function fakeDiscovery(): void
{
    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource*' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
            'scopes_supported' => ['mcp:read'],
        ]), 200, ['Content-Type' => 'application/json']),
        'https://auth.example.com/.well-known/oauth-authorization-server*' => Http::response(json_encode([
            'issuer' => 'https://auth.example.com',
            'token_endpoint' => 'https://auth.example.com/token',
            'grant_types_supported' => ['client_credentials', 'refresh_token'],
        ]), 200, ['Content-Type' => 'application/json']),
    ]);
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
    $store = new InMemoryTokenStore;

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('access-one')
        ->and($store->get('mcp-auth:notion'))->not->toBeNull()
        ->and($store->get('mcp-auth:notion')->accessToken)->toBe('access-one');

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
    $store = new InMemoryTokenStore;
    $store->put('mcp-auth:notion', new TokenSet('cached-one', null, time() + 600, 'mcp:read'));

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: $store,
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
    $store = new InMemoryTokenStore;
    $store->put('mcp-auth:notion', new TokenSet('stale', 'refresh-1', time() - 60, null));

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('refreshed')
        ->and($store->get('mcp-auth:notion')->refreshToken)->toBe('refresh-2');

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
    $store = new InMemoryTokenStore;
    $store->put('mcp-auth:notion', new TokenSet('stale', 'refresh-1', time() - 60, null));

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: $store,
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
    $store = new InMemoryTokenStore;
    $store->put('mcp-auth:notion', new TokenSet('stale', null, time() - 60, null));

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: $store,
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
    $store = new InMemoryTokenStore;

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: 'mcp:read',
        tokens: $store,
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
    $store = new InMemoryTokenStore;

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: $store,
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
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: new InMemoryTokenStore,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect($handler->bearerToken())->toBe('no-scope');
    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body)->not->toHaveKey('scope');
});

it('returns null from bearerTokenIfCached when no token is stored', function (): void {
    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: new InMemoryTokenStore,
        discovery: new AuthServerDiscovery,
    );

    expect($handler->bearerTokenIfCached())->toBeNull();
    Http::assertNothingSent();
});

it('returns the cached token from bearerTokenIfCached without touching the network', function (): void {
    $store = new InMemoryTokenStore;
    $store->put('mcp-auth:notion', new TokenSet('warm', null, time() + 600, null));

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: $store,
        discovery: new AuthServerDiscovery,
    );

    expect($handler->bearerTokenIfCached())->toBe('warm');
});

it('forgets the cached token entry', function (): void {
    $store = new InMemoryTokenStore;
    $store->put('mcp-auth:notion', new TokenSet('warm', null, time() + 600, null));

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: null,
        tokens: $store,
        discovery: new AuthServerDiscovery,
    );

    $handler->forget();

    expect($store->get('mcp-auth:notion'))->toBeNull();
});

it('wraps an identity provider failure in an OAuthException', function (): void {
    fakeDiscovery();

    $guzzle = makeGuzzleClient([
        new PsrResponse(401, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_client'])),
    ]);

    $handler = new OAuthHandler(
        registeredName: 'notion',
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'wrong',
        configuredScope: null,
        tokens: new InMemoryTokenStore,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    expect(fn (): string => $handler->bearerToken())
        ->toThrow(OAuthException::class, 'failed the client_credentials grant');
});

it('uses the inline storage key when no registered name is set', function (): void {
    fakeDiscovery();

    $guzzle = makeGuzzleClient([tokenResponse('inline-access')]);
    $store = new InMemoryTokenStore;

    $handler = new OAuthHandler(
        registeredName: null,
        mcpUrl: 'https://mcp.example.com/mcp',
        clientId: 'id',
        clientSecret: 'secret',
        configuredScope: 'mcp:read',
        tokens: $store,
        discovery: new AuthServerDiscovery,
        httpClient: $guzzle,
    );

    $handler->bearerToken();

    expect($store->get('mcp-auth:inline'))->not->toBeNull();
});
