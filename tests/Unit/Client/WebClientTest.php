<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Auth\TokenSet;
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
