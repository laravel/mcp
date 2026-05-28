<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository as RepositoryContract;
use Laravel\Mcp\Client\Auth\OAuthClientStateStore;
use Laravel\Mcp\Client\Auth\OAuthSession;
use Laravel\Mcp\Exceptions\OAuthException;

it('stores and pulls an OAuth session by state', function (): void {
    $store = new OAuthClientStateStore(new CacheRepository(new ArrayStore(serializesValues: true)));

    $session = new OAuthSession(
        serverName: 'notion',
        pkceVerifier: 'verifier-abc',
        userKey: null,
        intendedUrl: '/dashboard',
        scope: 'mcp:read',
    );

    $store->put('state-1', $session);

    $retrieved = $store->pull('state-1');

    expect($retrieved)->toBeInstanceOf(OAuthSession::class)
        ->and($retrieved->pkceVerifier)->toBe('verifier-abc')
        ->and($retrieved->intendedUrl)->toBe('/dashboard');

    expect($store->pull('state-1'))->toBeNull();
});

it('returns null when the cached payload is missing required fields', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $repo->put('mcp-oauth-state:state-2', ['malformed' => true], 60);

    $store = new OAuthClientStateStore($repo);

    expect($store->pull('state-2'))->toBeNull();
});

it('throws an OAuthException when the state cannot be persisted', function (): void {
    $repo = Mockery::mock(RepositoryContract::class);
    $repo->shouldReceive('put')->andThrow(new RuntimeException('cache unavailable'));

    $store = new OAuthClientStateStore($repo);

    expect(fn (): mixed => $store->put('state-3', new OAuthSession('notion', 'verifier-abc')))
        ->toThrow(OAuthException::class, 'Failed to persist OAuth state');
});
