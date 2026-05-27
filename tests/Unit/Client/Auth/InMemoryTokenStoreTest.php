<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Auth\InMemoryTokenStore;
use Laravel\Mcp\Client\Auth\TokenSet;

it('stores and retrieves tokens by key', function (): void {
    $store = new InMemoryTokenStore;
    $set = new TokenSet('a', null, 0, null);

    $store->put('mcp-auth:notion', $set);

    expect($store->get('mcp-auth:notion'))->toEqual($set)
        ->and($store->get('mcp-auth:other'))->toBeNull();
});

it('forgets tokens for the given key', function (): void {
    $store = new InMemoryTokenStore;
    $store->put('mcp-auth:notion', new TokenSet('a', null, 0, null));

    $store->forget('mcp-auth:notion');

    expect($store->get('mcp-auth:notion'))->toBeNull();
});

it('runs the locked closure immediately and returns its value', function (): void {
    $store = new InMemoryTokenStore;
    $count = 0;

    $result = $store->lock('mcp-auth:notion', function () use (&$count): string {
        $count++;

        return 'inside';
    });

    expect($result)->toBe('inside')
        ->and($count)->toBe(1);
});
