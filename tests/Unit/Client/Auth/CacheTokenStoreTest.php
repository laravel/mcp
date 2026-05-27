<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Repository as RepositoryContract;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Encryption\Encrypter as ConcreteEncrypter;
use Laravel\Mcp\Client\Auth\CacheTokenStore;
use Laravel\Mcp\Client\Auth\TokenSet;
use Laravel\Mcp\Exceptions\OAuthException;

function makeEncrypter(): ConcreteEncrypter
{
    return new ConcreteEncrypter(random_bytes(32), 'AES-256-CBC');
}

function makeStore(?RepositoryContract $repository = null, ?StringEncrypter $encrypter = null, int $lockWaitSeconds = 5): CacheTokenStore
{
    return new CacheTokenStore(
        cache: $repository ?? new CacheRepository(new ArrayStore(serializesValues: true)),
        crypt: $encrypter ?? makeEncrypter(),
        lockHoldSeconds: 10,
        lockWaitSeconds: $lockWaitSeconds,
    );
}

it('writes encrypted payloads that decrypt back into the original token set', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $store = makeStore($repo);
    $set = new TokenSet('access-abc', 'refresh-xyz', time() + 600, 'mcp:read');

    $store->put('mcp-auth:notion', $set);

    $raw = $repo->get('mcp-auth:notion');
    expect($raw)->toBeString()->and($raw)->not->toContain('access-abc');
    expect($store->get('mcp-auth:notion'))->toEqual($set);
});

it('returns null when no token has been stored', function (): void {
    $store = makeStore();

    expect($store->get('mcp-auth:none'))->toBeNull();
});

it('returns null when the cached payload cannot be decrypted', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $repo->put('mcp-auth:notion', 'not-a-real-encrypted-payload', 60);

    $store = makeStore($repo);

    expect($store->get('mcp-auth:notion'))->toBeNull();
});

it('forgets stored tokens for a given key', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $store = makeStore($repo);
    $store->put('mcp-auth:notion', new TokenSet('a', null, 0, null));

    $store->forget('mcp-auth:notion');

    expect($store->get('mcp-auth:notion'))->toBeNull();
});

it('applies the 30 second skew and 60 second floor to TTLs', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $store = makeStore($repo);

    $store->put('mcp-auth:long', new TokenSet('a', null, time() + 600, null));
    $store->put('mcp-auth:short', new TokenSet('a', null, time() + 5, null));

    $ttlLong = $repo->getStore()->get('mcp-auth:long') !== null ? 1 : 0;
    expect($ttlLong)->toBe(1);

    $ttlShort = $repo->getStore()->get('mcp-auth:short') !== null ? 1 : 0;
    expect($ttlShort)->toBe(1);
});

it('runs the locked closure inline for stores that do not support locking', function (): void {
    $store = makeStore();
    $ran = false;

    $result = $store->lock('mcp-auth:notion', function () use (&$ran): string {
        $ran = true;

        return 'done';
    });

    expect($ran)->toBeTrue()
        ->and($result)->toBe('done');
});

it('wraps a lock timeout in an OAuthException', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $store = makeStore($repo, lockWaitSeconds: 1);

    $repo->getStore()->lock('mcp-auth-refresh:mcp-auth:notion', 60, 'rival')->acquire();

    expect(fn (): mixed => $store->lock('mcp-auth:notion', fn (): string => 'nope'))
        ->toThrow(OAuthException::class, 'Timed out waiting for MCP token refresh lock');
});
