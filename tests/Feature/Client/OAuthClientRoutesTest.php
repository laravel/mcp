<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Client;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\WebClient;

beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
    ]);
    Mcp::oauthClientRoutes();
    Route::getRoutes()->refreshNameLookups();
});

function fakeAuthorizationCodeDiscovery(): void
{
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
}

it('connects to a registered MCP client by redirecting to the authorization server', function (): void {
    fakeAuthorizationCodeDiscovery();

    Mcp::registerClient('notion', fn (): WebClient => Client::web('https://mcp.example.com/mcp')->withOauth('cid-1')
    );

    $response = $this->get('/mcp/notion/connect');

    $response->assertStatus(302);

    expect($response->headers->get('Location'))->toStartWith('https://auth.example.com/authorize?');
});

it('returns 404 when connecting to an unregistered MCP client', function (): void {
    $this->get('/mcp/unknown/connect')->assertNotFound();
});

it('returns 404 when connecting to a client_credentials MCP client', function (): void {
    Mcp::registerClient('m2m', fn (): WebClient => Client::web('https://mcp.example.com/mcp')->withOauth('cid', 'secret')
    );

    $this->get('/mcp/m2m/connect')->assertNotFound();
});

it('completes the callback by exchanging the code for a token', function (): void {
    fakeAuthorizationCodeDiscovery();

    $history = [];
    $mock = new MockHandler([
        new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode([
            'access_token' => 'live-access',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ])),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    $guzzle = new GuzzleClient(['handler' => $stack]);

    Mcp::registerClient('notion', fn (): WebClient => Client::web('https://mcp.example.com/mcp')
        ->withOauth('cid-1')
        ->withOauthHttpClient($guzzle)
    );

    $connectResponse = $this->get('/mcp/notion/connect');
    parse_str((string) parse_url((string) $connectResponse->headers->get('Location'), PHP_URL_QUERY), $query);
    $state = (string) ($query['state'] ?? '');
    expect($state)->not->toBe('');

    $callbackResponse = $this->get('/mcp/notion/callback?code=auth-code-123&state='.$state);
    $callbackResponse->assertStatus(302);

    expect($history)->toHaveCount(1);
    parse_str((string) $history[0]['request']->getBody(), $body);
    expect($body['grant_type'])->toBe('authorization_code')
        ->and($body['code'])->toBe('auth-code-123');
});

it('ignores an off-host intended URL after a successful callback', function (): void {
    fakeAuthorizationCodeDiscovery();

    $mock = new MockHandler([
        new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode([
            'access_token' => 'live-access',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ])),
    ]);
    $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

    Mcp::registerClient('notion', fn (): WebClient => Client::web('https://mcp.example.com/mcp')
        ->withOauth('cid-1')
        ->withOauthHttpClient($guzzle)
    );

    $connectResponse = $this->get('/mcp/notion/connect?intended=https://evil.com/phish');
    parse_str((string) parse_url((string) $connectResponse->headers->get('Location'), PHP_URL_QUERY), $query);
    $state = (string) ($query['state'] ?? '');

    $callbackResponse = $this->get('/mcp/notion/callback?code=auth-code-123&state='.$state);

    $callbackResponse->assertStatus(302);

    expect($callbackResponse->headers->get('Location'))->not->toContain('evil.com');
});

it('honors a relative intended URL after a successful callback', function (): void {
    fakeAuthorizationCodeDiscovery();

    $mock = new MockHandler([
        new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode([
            'access_token' => 'live-access',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ])),
    ]);
    $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

    Mcp::registerClient('notion', fn (): WebClient => Client::web('https://mcp.example.com/mcp')
        ->withOauth('cid-1')
        ->withOauthHttpClient($guzzle)
    );

    $connectResponse = $this->get('/mcp/notion/connect?intended=/dashboard');
    parse_str((string) parse_url((string) $connectResponse->headers->get('Location'), PHP_URL_QUERY), $query);
    $state = (string) ($query['state'] ?? '');

    $callbackResponse = $this->get('/mcp/notion/callback?code=auth-code-123&state='.$state);

    $callbackResponse->assertStatus(302);

    expect($callbackResponse->headers->get('Location'))->toEndWith('/dashboard');
});

it('rejects a callback whose state was never issued in the current session', function (): void {
    Mcp::registerClient('notion', fn (): WebClient => Client::web('https://mcp.example.com/mcp')->withOauth('cid-1')
    );

    $response = $this->get('/mcp/notion/callback?code=auth-code-123&state=forged-state');

    $response->assertStatus(302);

    expect(session('mcp.oauth.error'))->toContain('did not match the current session');
});

it('rejects a callback whose state does not match the one issued at connect', function (): void {
    fakeAuthorizationCodeDiscovery();

    Mcp::registerClient('notion', fn (): WebClient => Client::web('https://mcp.example.com/mcp')->withOauth('cid-1')
    );

    $connectResponse = $this->get('/mcp/notion/connect');
    parse_str((string) parse_url((string) $connectResponse->headers->get('Location'), PHP_URL_QUERY), $query);
    $state = (string) ($query['state'] ?? '');
    expect($state)->not->toBe('');

    $response = $this->get('/mcp/notion/callback?code=auth-code-123&state='.$state.'-tampered');

    $response->assertStatus(302);

    expect(session('mcp.oauth.error'))->toContain('did not match the current session');
});

it('redirects with an error when the callback receives error parameters', function (): void {
    Mcp::registerClient('notion', fn (): WebClient => Client::web('https://mcp.example.com/mcp')->withOauth('cid-1')
    );

    $response = $this->get('/mcp/notion/callback?error=access_denied&error_description=user+rejected');

    $response->assertStatus(302);

    expect(session('mcp.oauth.error'))->toBe('user rejected');
});

it('redirects with an error when the callback is missing code or state', function (): void {
    Mcp::registerClient('notion', fn (): WebClient => Client::web('https://mcp.example.com/mcp')->withOauth('cid-1')
    );

    $response = $this->get('/mcp/notion/callback');

    $response->assertStatus(302);

    expect(session('mcp.oauth.error'))->toContain('missing');
});

it('returns 404 from the callback when the client is not registered', function (): void {
    $this->get('/mcp/unknown/callback?code=x&state=y')->assertNotFound();
});

it('registers named routes for connect and callback', function (): void {
    expect(Route::has('mcp.oauth.connect'))->toBeTrue()
        ->and(Route::has('mcp.oauth.callback'))->toBeTrue();
});
