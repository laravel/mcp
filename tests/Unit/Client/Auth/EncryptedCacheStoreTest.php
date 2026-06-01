<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository as RepositoryContract;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Encryption\Encrypter as ConcreteEncrypter;
use Laravel\Mcp\Client\Auth\EncryptedCacheStore;
use Laravel\Mcp\Exceptions\OAuthException;

function makeEncrypter(): ConcreteEncrypter
{
    return new ConcreteEncrypter(random_bytes(32), 'AES-256-CBC');
}

function makeStore(?RepositoryContract $repository = null, ?StringEncrypter $encrypter = null, int $lockWaitSeconds = 5): EncryptedCacheStore
{
    return new EncryptedCacheStore(
        cache: $repository ?? new CacheRepository(new ArrayStore(serializesValues: true)),
        crypt: $encrypter ?? makeEncrypter(),
        lockHoldSeconds: 10,
        lockWaitSeconds: $lockWaitSeconds,
    );
}

it('writes encrypted payloads that decrypt back into the original array', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $store = makeStore($repo);

    $store->put('mcp-auth:notion', ['access_token' => 'access-abc', 'scope' => 'mcp:read']);

    $raw = $repo->get('mcp-auth:notion');
    expect($raw)->toBeString()->and($raw)->not->toContain('access-abc');
    expect($store->get('mcp-auth:notion'))->toBe(['access_token' => 'access-abc', 'scope' => 'mcp:read']);
});

it('returns null when no entry has been stored', function (): void {
    expect(makeStore()->get('mcp-auth:none'))->toBeNull();
});

it('returns null when the cached payload cannot be decrypted', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $repo->put('mcp-auth:notion', 'not-a-real-encrypted-payload', 60);

    expect(makeStore($repo)->get('mcp-auth:notion'))->toBeNull();
});

it('pulls an entry and forgets it', function (): void {
    $store = makeStore();
    $store->put('mcp-oauth-state:abc', ['pkce_verifier' => 'verifier']);

    expect($store->pull('mcp-oauth-state:abc'))->toBe(['pkce_verifier' => 'verifier'])
        ->and($store->get('mcp-oauth-state:abc'))->toBeNull();
});

it('forgets a stored entry', function (): void {
    $store = makeStore();
    $store->put('mcp-auth:notion', ['access_token' => 'a']);

    $store->forget('mcp-auth:notion');

    expect($store->get('mcp-auth:notion'))->toBeNull();
});

it('persists entries written with an explicit ttl', function (): void {
    $captured = null;
    $repo = Mockery::mock(RepositoryContract::class);
    $repo->shouldReceive('put')->once()->andReturnUsing(function ($key, $value, $ttl) use (&$captured): bool {
        $captured = $ttl;

        return true;
    });

    makeStore($repo)->put('mcp-auth:notion', ['access_token' => 'a'], 1234);

    expect($captured)->toBe(1234);
});

it('stores entries forever when no ttl is provided', function (): void {
    $repo = Mockery::mock(RepositoryContract::class);
    $repo->shouldReceive('forever')->once()->andReturnTrue();

    makeStore($repo)->put('mcp-client:notion', ['client_id' => 'cid']);
});

it('throws an OAuthException when the cache write fails', function (): void {
    $repo = Mockery::mock(RepositoryContract::class);
    $repo->shouldReceive('put')->andThrow(new RuntimeException('cache down'));

    expect(fn (): mixed => makeStore($repo)->put('mcp-auth:notion', ['access_token' => 'a'], 60))
        ->toThrow(OAuthException::class, 'Failed to persist MCP OAuth cache entry');
});

it('runs the locked closure and returns its value', function (): void {
    $ran = false;

    $result = makeStore()->lock('mcp-auth-refresh:mcp-auth:notion', function () use (&$ran): string {
        $ran = true;

        return 'done';
    });

    expect($ran)->toBeTrue()->and($result)->toBe('done');
});

it('wraps a lock timeout in an OAuthException', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $store = makeStore($repo, lockWaitSeconds: 1);

    $repo->getStore()->lock('mcp-auth-refresh:mcp-auth:notion', 60, 'rival')->acquire();

    expect(fn (): mixed => $store->lock('mcp-auth-refresh:mcp-auth:notion', fn (): string => 'nope'))
        ->toThrow(OAuthException::class, 'Timed out waiting for MCP token refresh lock');
});
