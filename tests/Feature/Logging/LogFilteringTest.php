<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\LogLevel;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Support\LoggingManager;
use Laravel\Mcp\Server\Tool;
use Tests\Fixtures\ArrayTransport;

class LoggingTestServer extends Server
{
    protected array $tools = [
        LoggingTestTool::class,
        StructuredLogTool::class,
        LogLevelTestTool::class,
    ];

    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
        self::CAPABILITY_RESOURCES => [
            'listChanged' => false,
        ],
        self::CAPABILITY_PROMPTS => [
            'listChanged' => false,
        ],
        self::CAPABILITY_LOGGING => [],
    ];
}

class LoggingTestTool extends Tool
{
    public function handle(Request $request): \Generator
    {
        yield Response::log(LogLevel::Emergency, 'Emergency message');
        yield Response::log(LogLevel::Error, 'Error message');
        yield Response::log(LogLevel::Warning, 'Warning message');
        yield Response::log(LogLevel::Info, 'Info message');
        yield Response::log(LogLevel::Debug, 'Debug message');

        yield Response::text('Processing complete');
    }
}

class StructuredLogTool extends Tool
{
    public function handle(Request $request): \Generator
    {
        yield Response::log(
            LogLevel::Error,
            ['error' => 'Connection failed', 'host' => 'localhost', 'port' => 5432],
            'database'
        );

        yield Response::log(
            LogLevel::Info,
            'Query executed successfully',
            'database'
        );

        yield Response::text('Database operation complete');
    }
}

class LogLevelTestTool extends Tool
{
    public function handle(Request $request, LoggingManager $logManager): \Generator
    {
        yield Response::log(LogLevel::Warning, 'This is a warning message');
        yield Response::log(LogLevel::Emergency, 'This is an emergency message');

        $level = $logManager->getLevel();
        yield Response::text('Here is the Log Level: '.$level->value);
    }
}

it('sends all log levels with the default info level', function (): void {
    $response = LoggingTestServer::tool(LoggingTestTool::class);

    $response->assertLogCount(4)
        ->assertLogSent(LogLevel::Emergency, 'Emergency message')
        ->assertLogSent(LogLevel::Error, 'Error message')
        ->assertLogSent(LogLevel::Warning, 'Warning message')
        ->assertLogSent(LogLevel::Info, 'Info message')
        ->assertLogNotSent(LogLevel::Debug);
});

it('handles structured log data with arrays', function (): void {
    $response = LoggingTestServer::tool(StructuredLogTool::class);

    $response->assertLogCount(2)
        ->assertLogSent(LogLevel::Error)
        ->assertLogSent(LogLevel::Info);
});

it('supports string and array data in logs', function (): void {
    $response = LoggingTestServer::tool(StructuredLogTool::class);

    $response->assertSentNotification('notifications/message')
        ->assertLogCount(2);
});

it('filters logs correctly when the log level is set to critical', function (): void {
    $transport = new ArrayTransport;
    $server = new LoggingTestServer($transport);
    $server->start();

    $sessionId = 'test-session-'.uniqid();
    $transport->sessionId = $sessionId;

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'logging/setLevel',
        'params' => ['level' => 'critical'],
    ]));

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'log-level-test-tool',
            'arguments' => [],
        ],
    ]));

    $logNotifications = collect($transport->sent)
        ->map(fn ($msg): mixed => json_decode((string) $msg, true))
        ->filter(fn ($msg): bool => isset($msg['method']) && $msg['method'] === 'notifications/message')
        ->filter(fn ($msg): bool => isset($msg['params']['level']));

    expect($logNotifications->count())->toBe(1);

    $emergencyLog = $logNotifications->first(fn ($msg): bool => $msg['params']['level'] === 'emergency');
    expect($emergencyLog)->not->toBeNull();
    expect($emergencyLog['params']['data'])->toBe('This is an emergency message');

    $warningLog = $logNotifications->first(fn ($msg): bool => $msg['params']['level'] === 'warning');
    expect($warningLog)->toBeNull();

    $toolResponse = collect($transport->sent)
        ->map(fn ($msg): mixed => json_decode((string) $msg, true))
        ->first(fn ($msg): bool => isset($msg['id']) && $msg['id'] === 2);

    expect($toolResponse['result']['content'][0]['text'])->toContain('critical');
});

it('filters logs correctly with default info log level', function (): void {
    $transport = new ArrayTransport;
    $server = new LoggingTestServer($transport);
    $server->start();

    $sessionId = 'test-session-'.uniqid();
    $transport->sessionId = $sessionId;

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'log-level-test-tool',
            'arguments' => [],
        ],
    ]));

    $logNotifications = collect($transport->sent)
        ->map(fn ($msg): mixed => json_decode((string) $msg, true))
        ->filter(fn ($msg): bool => isset($msg['method']) && $msg['method'] === 'notifications/message')
        ->filter(fn ($msg): bool => isset($msg['params']['level']));

    expect($logNotifications->count())->toBe(2);

    $emergencyLog = $logNotifications->first(fn ($msg): bool => $msg['params']['level'] === 'emergency');
    expect($emergencyLog)->not->toBeNull();
    expect($emergencyLog['params']['data'])->toBe('This is an emergency message');

    $warningLog = $logNotifications->first(fn ($msg): bool => $msg['params']['level'] === 'warning');
    expect($warningLog)->not->toBeNull();
    expect($warningLog['params']['data'])->toBe('This is a warning message');

    $toolResponse = collect($transport->sent)
        ->map(fn ($msg): mixed => json_decode((string) $msg, true))
        ->first(fn ($msg): bool => isset($msg['id']) && $msg['id'] === 1);

    expect($toolResponse['result']['content'][0]['text'])->toContain('info');
});
