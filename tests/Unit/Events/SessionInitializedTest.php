<?php

use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Events\SessionInitialized;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;

it('dispatches SessionInitialized event on initialize', function (): void {
    Event::fake([SessionInitialized::class]);

    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-26',
            'clientInfo' => [
                'name' => 'claude-desktop',
                'title' => 'Claude Desktop',
                'version' => '1.0.0',
            ],
            'capabilities' => [
                'sampling' => [],
            ],
        ],
    ]);

    ($transport->handler)($payload);

    Event::assertDispatched(SessionInitialized::class, fn (SessionInitialized $event): bool => $event->clientInfo['name'] === 'claude-desktop'
        && $event->clientInfo['title'] === 'Claude Desktop'
        && $event->clientInfo['version'] === '1.0.0'
        && $event->protocolVersion === '2025-03-26'
        && $event->clientCapabilities === ['sampling' => []]
        && $event->sessionId !== null);
});

it('dispatches SessionInitialized event with null values when not provided', function (): void {
    Event::fake([SessionInitialized::class]);

    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    Event::assertDispatched(SessionInitialized::class, fn (SessionInitialized $event): bool => $event->clientInfo === null
        && $event->protocolVersion === null
        && $event->clientCapabilities === null
        && $event->sessionId !== null);
});

it('provides helper methods on SessionInitialized event', function (): void {
    $event = new SessionInitialized(
        sessionId: 'test-session-id',
        clientInfo: [
            'name' => 'cursor',
            'title' => 'Cursor',
            'version' => '2.0.0',
        ],
        protocolVersion: '2025-03-26',
        clientCapabilities: ['sampling' => []],
    );

    expect($event->clientName())->toBe('cursor')
        ->and($event->clientTitle())->toBe('Cursor')
        ->and($event->clientVersion())->toBe('2.0.0')
        ->and($event->sessionId)->toBe('test-session-id')
        ->and($event->protocolVersion)->toBe('2025-03-26');
});

it('returns null for helper methods when clientInfo is null', function (): void {
    $event = new SessionInitialized(
        sessionId: 'test-session-id',
        clientInfo: null,
        protocolVersion: null,
        clientCapabilities: null,
    );

    expect($event->clientName())->toBeNull()
        ->and($event->clientTitle())->toBeNull()
        ->and($event->clientVersion())->toBeNull();
});

it('returns null for helper methods when fields are missing', function (): void {
    $event = new SessionInitialized(
        sessionId: 'test-session-id',
        clientInfo: ['other' => 'data'],
        protocolVersion: null,
        clientCapabilities: null,
    );

    expect($event->clientName())->toBeNull()
        ->and($event->clientTitle())->toBeNull()
        ->and($event->clientVersion())->toBeNull();
});
