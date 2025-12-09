<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\LogLevel;
use Laravel\Mcp\Server\Content\Log;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

it('creates a log with level and data', function (): void {
    $message = new Log(LogLevel::Error, 'Something went wrong');

    expect($message->level())->toBe(LogLevel::Error)
        ->and($message->data())->toBe('Something went wrong')
        ->and($message->logger())->toBeNull();
});

it('creates a log with an optional logger name', function (): void {
    $message = new Log(LogLevel::Info, 'Database connected', 'database');

    expect($message->level())->toBe(LogLevel::Info)
        ->and($message->data())->toBe('Database connected')
        ->and($message->logger())->toBe('database');
});

it('converts to array with the correct notification format', function (): void {
    $withoutLogger = new Log(LogLevel::Warning, 'Low disk space');
    $withLogger = new Log(LogLevel::Debug, 'Query executed', 'sql');

    expect($withoutLogger->toArray())->toEqual([
        'method' => 'notifications/message',
        'params' => [
            'level' => 'warning',
            'data' => 'Low disk space',
        ],
    ])->and($withLogger->toArray())->toEqual([
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
    $message = new Log(LogLevel::Error, $data);

    expect($message->data())->toBe($data)
        ->and($message->toArray()['params']['data'])->toBe($data);
});

it('supports object data', function (): void {
    $data = (object) ['name' => 'test', 'value' => 42];
    $message = new Log(LogLevel::Info, $data);

    expect($message->data())->toEqual($data);
});

it('casts to string as method name', function (): void {
    $message = new Log(LogLevel::Info, 'Test message');

    expect((string) $message)->toBe('notifications/message');
});

it('may be used in primitives', function (): void {
    $message = new Log(LogLevel::Info, 'Processing');

    $tool = $message->toTool(new class extends Tool {});
    $prompt = $message->toPrompt(new class extends Prompt {});
    $resource = $message->toResource(new class extends Resource
    {
        protected string $uri = 'file://test.txt';

        protected string $name = 'test';

        protected string $title = 'Test File';

        protected string $mimeType = 'text/plain';
    });

    $expected = [
        'method' => 'notifications/message',
        'params' => [
            'level' => 'info',
            'data' => 'Processing',
        ],
    ];

    expect($tool)->toEqual($expected)
        ->and($prompt)->toEqual($expected)
        ->and($resource)->toEqual($expected);
});

it('supports _meta via setMeta', function (): void {
    $withMeta = new Log(LogLevel::Error, 'Error occurred');
    $withMeta->setMeta(['trace_id' => 'abc123']);

    $withoutMeta = new Log(LogLevel::Info, 'Test');

    expect($withMeta->toArray())->toEqual([
        'method' => 'notifications/message',
        'params' => [
            'level' => 'error',
            'data' => 'Error occurred',
            '_meta' => ['trace_id' => 'abc123'],
        ],
    ])->and($withoutMeta->toArray()['params'])->not->toHaveKey('_meta');
});

it('supports all log levels', function (LogLevel $level, string $expected): void {
    $message = new Log($level, 'Test');

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
