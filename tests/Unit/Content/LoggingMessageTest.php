<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Content\LogNotification;
use Laravel\Mcp\Server\Enums\LogLevel;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('creates a logging message with level and data', function (): void {
    $message = new LogNotification(LogLevel::Error, 'Something went wrong');

    expect($message->level())->toBe(LogLevel::Error)
        ->and($message->data())->toBe('Something went wrong')
        ->and($message->logger())->toBeNull();
});

it('creates a logging message with optional logger name', function (): void {
    $message = new LogNotification(LogLevel::Info, 'Database connected', 'database');

    expect($message->level())->toBe(LogLevel::Info)
        ->and($message->data())->toBe('Database connected')
        ->and($message->logger())->toBe('database');
});

it('converts to array with correct notification format', function (): void {
    $message = new LogNotification(LogLevel::Warning, 'Low disk space');

    expect($message->toArray())->toEqual([
        'method' => 'notifications/message',
        'params' => [
            'level' => 'warning',
            'data' => 'Low disk space',
        ],
    ]);
});

it('includes logger in params when provided', function (): void {
    $message = new LogNotification(LogLevel::Debug, 'Query executed', 'sql');

    expect($message->toArray())->toEqual([
        'method' => 'notifications/message',
        'params' => [
            'level' => 'debug',
            'data' => 'Query executed',
            'logger' => 'sql',
        ],
    ]);
});

it('supports array data', function (): void {
    $data = ['error' => 'Connection failed', 'host' => 'localhost', 'port' => 5432];
    $message = new LogNotification(LogLevel::Error, $data);

    expect($message->data())->toBe($data)
        ->and($message->toArray()['params']['data'])->toBe($data);
});

it('supports object data', function (): void {
    $data = (object) ['name' => 'test', 'value' => 42];
    $message = new LogNotification(LogLevel::Info, $data);

    expect($message->data())->toEqual($data);
});

it('casts to string as method name', function (): void {
    $message = new LogNotification(LogLevel::Info, 'Test message');

    expect((string) $message)->toBe('notifications/message');
});

it('may be used in tools', function (): void {
    $message = new LogNotification(LogLevel::Info, 'Processing');

    $payload = $message->toTool(new class extends Tool {});

    expect($payload)->toEqual([
        'method' => 'notifications/message',
        'params' => [
            'level' => 'info',
            'data' => 'Processing',
        ],
    ]);
});

it('may be used in prompts', function (): void {
    $message = new LogNotification(LogLevel::Warning, 'Deprecation notice');

    $payload = $message->toPrompt(new class extends Prompt {});

    expect($payload)->toEqual([
        'method' => 'notifications/message',
        'params' => [
            'level' => 'warning',
            'data' => 'Deprecation notice',
        ],
    ]);
});

it('may be used in resources', function (): void {
    $message = new LogNotification(LogLevel::Debug, 'Resource loaded');
    $resource = new class extends Resource
    {
        protected string $uri = 'file://test.txt';

        protected string $name = 'test';

        protected string $title = 'Test File';

        protected string $mimeType = 'text/plain';
    };

    $payload = $message->toResource($resource);

    expect($payload)->toEqual([
        'method' => 'notifications/message',
        'params' => [
            'level' => 'debug',
            'data' => 'Resource loaded',
        ],
    ]);
});

it('supports _meta via setMeta', function (): void {
    $message = new LogNotification(LogLevel::Error, 'Error occurred');
    $message->setMeta(['trace_id' => 'abc123']);

    expect($message->toArray())->toEqual([
        'method' => 'notifications/message',
        'params' => [
            'level' => 'error',
            'data' => 'Error occurred',
            '_meta' => ['trace_id' => 'abc123'],
        ],
    ]);
});

it('does not include _meta if not set', function (): void {
    $message = new LogNotification(LogLevel::Info, 'Test');

    expect($message->toArray()['params'])->not->toHaveKey('_meta');
});

it('supports all log levels', function (LogLevel $level, string $expected): void {
    $message = new LogNotification($level, 'Test');

    expect($message->toArray()['params']['level'])->toBe($expected);
})->with([
    [LogLevel::Emergency, 'emergency'],
    [LogLevel::Alert, 'alert'],
    [LogLevel::Critical, 'critical'],
    [LogLevel::Error, 'error'],
    [LogLevel::Warning, 'warning'],
    [LogLevel::Notice, 'notice'],
    [LogLevel::Info, 'info'],
    [LogLevel::Debug, 'debug'],
]);
