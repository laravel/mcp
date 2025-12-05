<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Enums\LogLevel;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\SetLogLevel;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Support\LoggingManager;
use Laravel\Mcp\Server\Support\SessionStore;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

it('sets log level successfully', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => [
            'level' => 'debug',
        ],
    ], 'session-123');

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-06-18'],
        serverCapabilities: [Server::CAPABILITY_LOGGING => []],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $loggingManager = new LoggingManager(new SessionStore(Cache::driver(), 'session-123'));
    $method = new SetLogLevel($loggingManager);

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1)
        ->and($payload['result'])->toEqual((object) []);

    $manager = new LoggingManager(new SessionStore(Cache::driver(), 'session-123'));
    expect($manager->getLevel())->toBe(LogLevel::Debug);
});

it('handles all valid log levels', function (string $levelString, LogLevel $expectedLevel): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => [
            'level' => $levelString,
        ],
    ], 'session-456');

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-06-18'],
        serverCapabilities: [Server::CAPABILITY_LOGGING => []],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $loggingManager = new LoggingManager(new SessionStore(Cache::driver(), 'session-456'));
    $method = new SetLogLevel($loggingManager);
    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);

    $manager = new LoggingManager(new SessionStore(Cache::driver(), 'session-456'));
    expect($manager->getLevel())->toBe($expectedLevel);
})->with([
    ['emergency', LogLevel::Emergency],
    ['alert', LogLevel::Alert],
    ['critical', LogLevel::Critical],
    ['error', LogLevel::Error],
    ['warning', LogLevel::Warning],
    ['notice', LogLevel::Notice],
    ['info', LogLevel::Info],
    ['debug', LogLevel::Debug],
]);

it('throws an exception for a missing level parameter', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid Request: The [level] parameter is required and must be a string.');
    $this->expectExceptionCode(-32602);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => [],
    ], 'session-789');

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-06-18'],
        serverCapabilities: [Server::CAPABILITY_LOGGING => []],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $loggingManager = new LoggingManager(new SessionStore(Cache::driver(), 'session-789'));
    $method = new SetLogLevel($loggingManager);

    try {
        $method->handle($request, $context);
    } catch (JsonRpcException $jsonRpcException) {
        $error = $jsonRpcException->toJsonRpcResponse()->toArray();

        expect($error)->toEqual([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32602,
                'message' => 'Invalid Request: The [level] parameter is required and must be a string.',
            ],
        ]);

        throw $jsonRpcException;
    }
});

it('throws exception for invalid level', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Invalid log level [invalid]');
    $this->expectExceptionCode(-32602);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => [
            'level' => 'invalid',
        ],
    ], 'session-999');

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-06-18'],
        serverCapabilities: [Server::CAPABILITY_LOGGING => []],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $loggingManager = new LoggingManager(new SessionStore(Cache::driver(), 'session-999'));
    $method = new SetLogLevel($loggingManager);

    try {
        $method->handle($request, $context);
    } catch (JsonRpcException $jsonRpcException) {
        $error = $jsonRpcException->toJsonRpcResponse()->toArray();

        expect($error['error']['code'])->toEqual(-32602)
            ->and($error['error']['message'])->toContain('Invalid log level [invalid]');

        throw $jsonRpcException;
    }
});

it('throws exception for non-string level parameter', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionCode(-32602);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => [
            'level' => 123,
        ],
    ], 'session-111');

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-06-18'],
        serverCapabilities: [Server::CAPABILITY_LOGGING => []],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $loggingManager = new LoggingManager(new SessionStore(Cache::driver(), 'session-111'));
    $method = new SetLogLevel($loggingManager);
    $method->handle($request, $context);
});

it('throws an exception when logging capability is not enabled', function (): void {
    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Logging capability is not enabled on this server.');
    $this->expectExceptionCode(-32601);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => [
            'level' => 'debug',
        ],
    ], 'session-222');

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-06-18'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [],
    );

    $loggingManager = new LoggingManager(new SessionStore(Cache::driver(), 'session-222'));
    $method = new SetLogLevel($loggingManager);
    $method->handle($request, $context);
});
