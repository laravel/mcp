<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Laravel\Mcp\Client\Auth\OAuthClientStateStore;

it('stores and pulls an OAuth state payload', function (): void {
    $store = new OAuthClientStateStore(new CacheRepository(new ArrayStore(serializesValues: true)));

    $store->put('state-1', [
        'server' => 'notion',
        'user_key' => null,
        'pkce_verifier' => 'verifier-abc',
        'intended_url' => '/dashboard',
        'scope' => 'mcp:read',
    ]);

    $payload = $store->pull('state-1');

    expect($payload)->not->toBeNull()
        ->and($payload['pkce_verifier'])->toBe('verifier-abc')
        ->and($payload['intended_url'])->toBe('/dashboard');

    expect($store->pull('state-1'))->toBeNull();
});

it('returns null when payload is missing required fields', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $repo->put('mcp-oauth-state:state-2', ['malformed' => true], 60);

    $store = new OAuthClientStateStore($repo);

    expect($store->pull('state-2'))->toBeNull();
});
