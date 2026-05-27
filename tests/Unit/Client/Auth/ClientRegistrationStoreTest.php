<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Encryption\Encrypter as ConcreteEncrypter;
use Laravel\Mcp\Client\Auth\CacheClientRegistrationStore;
use Laravel\Mcp\Client\Auth\ClientRegistration;
use Laravel\Mcp\Client\Auth\InMemoryClientRegistrationStore;

function makeRegistrationEncrypter(): ConcreteEncrypter
{
    return new ConcreteEncrypter(random_bytes(32), 'AES-256-CBC');
}

it('stores and retrieves a registration via the in-memory store', function (): void {
    $store = new InMemoryClientRegistrationStore;
    $registration = new ClientRegistration('cid-1', 'secret', 1_700_000_000, 0);

    $store->put('mcp-client:notion', $registration);

    expect($store->get('mcp-client:notion'))->toEqual($registration)
        ->and($store->get('mcp-client:other'))->toBeNull();
});

it('forgets a registration from the in-memory store', function (): void {
    $store = new InMemoryClientRegistrationStore;
    $store->put('mcp-client:notion', new ClientRegistration('cid'));

    $store->forget('mcp-client:notion');

    expect($store->get('mcp-client:notion'))->toBeNull();
});

it('writes an encrypted payload via the cache store that round-trips back to the registration', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $store = new CacheClientRegistrationStore($repo, makeRegistrationEncrypter());

    $registration = new ClientRegistration('cid-secret', 'super-secret', 1_700_000_000, 1_700_010_000);
    $store->put('mcp-client:notion', $registration);

    $raw = $repo->get('mcp-client:notion');
    expect($raw)->toBeString()->and($raw)->not->toContain('super-secret');

    expect($store->get('mcp-client:notion'))->toEqual($registration);
});

it('returns null when the cached registration payload cannot be decrypted', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $repo->put('mcp-client:notion', 'corrupted-ciphertext', 3600);

    $store = new CacheClientRegistrationStore($repo, makeRegistrationEncrypter());

    expect($store->get('mcp-client:notion'))->toBeNull();
});

it('forgets a stored registration via the cache store', function (): void {
    $repo = new CacheRepository(new ArrayStore(serializesValues: true));
    $store = new CacheClientRegistrationStore($repo, makeRegistrationEncrypter());
    $store->put('mcp-client:notion', new ClientRegistration('cid'));

    $store->forget('mcp-client:notion');

    expect($store->get('mcp-client:notion'))->toBeNull();
});
