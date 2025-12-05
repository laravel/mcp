<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Server\Support\SessionStore;

beforeEach(function (): void {
    Cache::flush();
});

test('it can set and get values for a session', function (): void {
    $session = new SessionStore(Cache::driver(), 'session-1');

    $session->set('key', 'value');

    expect($session->get('key'))->toBe('value');
});

test('it returns the default value when the key does not exist', function (): void {
    $session = new SessionStore(Cache::driver(), 'session-1');

    expect($session->get('nonexistent', 'default'))->toBe('default');
});

test('it maintains separate values for different sessions', function (): void {
    $session1 = new SessionStore(Cache::driver(), 'session-1');
    $session2 = new SessionStore(Cache::driver(), 'session-2');

    $session1->set('key', 'value-1');
    $session2->set('key', 'value-2');

    expect($session1->get('key'))->toBe('value-1')
        ->and($session2->get('key'))->toBe('value-2');
});

test('it can check if a key exists', function (): void {
    $session = new SessionStore(Cache::driver(), 'session-1');

    expect($session->has('key'))->toBeFalse();

    $session->set('key', 'value');

    expect($session->has('key'))->toBeTrue();
});

test('it can forget a key', function (): void {
    $session = new SessionStore(Cache::driver(), 'session-1');

    $session->set('key', 'value');

    expect($session->has('key'))->toBeTrue();

    $session->forget('key');
    expect($session->has('key'))->toBeFalse();
});

test('it returns session id', function (): void {
    $session = new SessionStore(Cache::driver(), 'my-session-id');

    expect($session->sessionId())->toBe('my-session-id');
});

test('it handles null session id gracefully', function (): void {
    $session = new SessionStore(Cache::driver());

    $session->set('key', 'value');
    $session->forget('key');

    expect($session->get('key', 'default'))->toBe('default')
        ->and($session->has('key'))->toBeFalse();
});

test('it can store complex values', function (): void {
    $session = new SessionStore(Cache::driver(), 'session-1');

    $session->set('array', ['foo' => 'bar', 'baz' => [1, 2, 3]]);
    $session->set('object', (object) ['name' => 'test']);

    expect($session->get('array'))->toBe(['foo' => 'bar', 'baz' => [1, 2, 3]])
        ->and($session->get('object'))->toEqual((object) ['name' => 'test']);
});
