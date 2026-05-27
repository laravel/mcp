<?php

declare(strict_types=1);

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Auth\TokenSet;
use Laravel\Mcp\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Exceptions\UserIdentityRequiredException;
use Laravel\Mcp\WebClient;

it('throws when withToken() is called after oauth()', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth('id', 'secret');

    expect(fn (): WebClient => $client->withToken('bearer'))
        ->toThrow(InvalidArgumentException::class, 'Cannot call withToken() after oauth()');
});

it('throws when oauth() is called after withToken()', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->withToken('bearer');

    expect(fn (): WebClient => $client->oauth('id', 'secret'))
        ->toThrow(InvalidArgumentException::class, 'Cannot call oauth() after withToken()');
});

it('throws when oauth() is called twice on the same client', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth('id', 'secret');

    expect(fn (): WebClient => $client->oauth('id', 'secret'))
        ->toThrow(InvalidArgumentException::class, 'OAuth has already been configured');
});

it('returns null tokens() and no-ops forgetTokens() when oauth is not configured', function (): void {
    $client = Client::web('https://mcp.example.com/mcp');

    expect($client->tokens())->toBeNull();
    $client->forgetTokens();
});

it('returns null tokens() and no-ops forgetTokens() on a stdio client', function (): void {
    $client = Client::local('echo');

    expect($client->tokens())->toBeNull();
    $client->forgetTokens();
});

it('returns null tokens() and no-ops forgetTokens() after oauth() when no token has been granted', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth('id', 'secret');

    expect($client->tokens())->toBeNull();
    $client->forgetTokens();
});

it('builds a WebClient with the URL stored on the instance', function (): void {
    expect(Client::web('https://mcp.example.com/mcp'))->toBeInstanceOf(WebClient::class);
});

it('cleanly handles the TokenSet round-trip through fromArray', function (): void {
    $set = new TokenSet('a', null, 0, null);
    expect(TokenSet::fromArray($set->toArray())->accessToken)->toBe('a');
});

it('reports needsAuthorization() as false on a base Client (no oauth)', function (): void {
    $client = Client::web('https://mcp.example.com/mcp');

    expect($client->needsAuthorization())->toBeFalse();
});

it('reports needsAuthorization() as false for a client_credentials WebClient', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth('id', 'secret');

    expect($client->needsAuthorization())->toBeFalse();
});

it('reports needsAuthorization() as true for an authorization_code WebClient with no token', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth('id');

    expect($client->needsAuthorization())->toBeTrue();
});

it('throws when forUser() is called on a client_credentials client', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth('id', 'secret');

    expect(fn (): WebClient => $client->forUser('42'))
        ->toThrow(InvalidArgumentException::class, 'authorization_code OAuth clients');
});

it('throws when forUser() is called without OAuth configured', function (): void {
    $client = Client::web('https://mcp.example.com/mcp');

    expect(fn (): WebClient => $client->forUser('42'))
        ->toThrow(InvalidArgumentException::class, 'authorization_code OAuth clients');
});

it('throws when forUser(null) is passed explicitly', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth('id');

    expect(fn (): WebClient => $client->forUser(null))
        ->toThrow(InvalidArgumentException::class, 'null is not allowed');
});

it('returns a fresh WebClient from forUser() that is not the original instance', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth('id');

    $scoped = $client->forUser('42');

    expect($scoped)->not->toBe($client)
        ->and($scoped)->toBeInstanceOf(WebClient::class);
});

it('throws UserIdentityRequiredException when a forUser closure resolves to null', function (): void {
    Http::fake([
        'https://mcp.example.com/.well-known/oauth-protected-resource*' => Http::response(json_encode([
            'resource' => 'https://mcp.example.com/mcp',
            'authorization_servers' => ['https://auth.example.com'],
        ]), 200, ['Content-Type' => 'application/json']),
    ]);

    $client = Client::web('https://mcp.example.com/mcp')
        ->oauth('id')
        ->forUser(fn (): ?string => null);

    expect(fn (): bool => $client->needsAuthorization())
        ->toThrow(UserIdentityRequiredException::class);
});

it('invokes the onAuthRequired callback and wraps a Response in HttpResponseException', function (): void {
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

    $client = Client::web('https://mcp.example.com/mcp')
        ->oauth('id', null, null, fn (AuthorizationRequiredException $e) => redirect()->away($e->authorizationUrl()))
        ->asRegisteredClient('notion', 3600);

    try {
        $client->tools();
        $this->fail('Expected HttpResponseException');
    } catch (HttpResponseException $httpResponseException) {
        expect($httpResponseException->getResponse()->getStatusCode())->toBe(302)
            ->and($httpResponseException->getResponse()->headers->get('Location'))->toStartWith('https://auth.example.com/authorize?');
    }
});

it('allows oauth() without a client_id (dynamic client registration)', function (): void {
    $client = Client::web('https://mcp.example.com/mcp')->oauth();

    expect($client)->toBeInstanceOf(WebClient::class)
        ->and($client->needsAuthorization())->toBeTrue();
});

it('throws when oauth() is called with a secret but no client_id', function (): void {
    $client = Client::web('https://mcp.example.com/mcp');

    expect(fn (): WebClient => $client->oauth(null, 'secret'))
        ->toThrow(InvalidArgumentException::class, 'Dynamic client registration cannot be combined with a client secret');
});

it('rethrows AuthorizationRequiredException when onAuthRequired returns nothing', function (): void {
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

    $called = false;
    $client = Client::web('https://mcp.example.com/mcp')
        ->oauth('id', null, null, function () use (&$called): void {
            $called = true;
        })
        ->asRegisteredClient('notion', 3600);

    expect(fn (): mixed => $client->tools())
        ->toThrow(AuthorizationRequiredException::class);
    expect($called)->toBeTrue();
});
