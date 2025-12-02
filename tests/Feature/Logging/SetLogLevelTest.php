<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Enums\LogLevel;
use Laravel\Mcp\Server\Support\LoggingManager;
use Laravel\Mcp\Server\Support\SessionStore;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;

it('persists log level to cache through server request flow', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $sessionId = 'test-session-'.uniqid();
    $transport->sessionId = $sessionId;

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => [
            'level' => 'error',
        ],
    ]);

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toHaveKey('result')
        ->and($response['id'])->toBe(1);

    $manager = new LoggingManager(new SessionStore(Cache::driver(), $sessionId));
    expect($manager->getLevel())->toBe(LogLevel::Error);
});

it('correctly isolates log levels per session', function (): void {
    $transport1 = new ArrayTransport;
    $server1 = new ExampleServer($transport1);
    $server1->start();

    $transport2 = new ArrayTransport;
    $server2 = new ExampleServer($transport2);
    $server2->start();

    $sessionId1 = 'session-1-'.uniqid();
    $sessionId2 = 'session-2-'.uniqid();

    $transport1->sessionId = $sessionId1;
    ($transport1->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => ['level' => 'debug'],
    ]));

    $transport2->sessionId = $sessionId2;
    ($transport2->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'logging/setLevel',
        'params' => ['level' => 'error'],
    ]));

    $manager1 = new LoggingManager(new SessionStore(Cache::driver(), $sessionId1));
    $manager2 = new LoggingManager(new SessionStore(Cache::driver(), $sessionId2));

    expect($manager1->getLevel())->toBe(LogLevel::Debug)
        ->and($manager2->getLevel())->toBe(LogLevel::Error);
});
