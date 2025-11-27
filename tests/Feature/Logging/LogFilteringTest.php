<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Enums\LogLevel;
use Laravel\Mcp\Server\LoggingManager;
use Laravel\Mcp\Server\Tool;

beforeEach(function (): void {
    LoggingManager::setDefaultLevel(LogLevel::Info);
});

class LoggingTestServer extends Server
{
    protected array $tools = [
        LoggingTestTool::class,
        StructuredLogTool::class,
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

it('sends all log levels with default level', function (): void {
    $response = LoggingTestServer::tool(LoggingTestTool::class);

    $response->assertLogCount(4)
        ->assertLogSent(LogLevel::Emergency, 'Emergency message')
        ->assertLogSent(LogLevel::Error, 'Error message')
        ->assertLogSent(LogLevel::Warning, 'Warning message')
        ->assertLogSent(LogLevel::Info, 'Info message')
        ->assertLogNotSent(LogLevel::Debug);
});

it('filters logs based on configured log level - error only', function (): void {
    LoggingManager::setDefaultLevel(LogLevel::Error);

    $response = LoggingTestServer::tool(LoggingTestTool::class);

    $response->assertLogCount(2)
        ->assertLogSent(LogLevel::Emergency)
        ->assertLogSent(LogLevel::Error)
        ->assertLogNotSent(LogLevel::Warning)
        ->assertLogNotSent(LogLevel::Info)
        ->assertLogNotSent(LogLevel::Debug);
});

it('filters logs based on the configured log level-debug shows all', function (): void {
    LoggingManager::setDefaultLevel(LogLevel::Debug);

    $response = LoggingTestServer::tool(LoggingTestTool::class);

    $response->assertLogCount(5)
        ->assertLogSent(LogLevel::Emergency)
        ->assertLogSent(LogLevel::Error)
        ->assertLogSent(LogLevel::Warning)
        ->assertLogSent(LogLevel::Info)
        ->assertLogSent(LogLevel::Debug);
});

it('handles structured log data with arrays', function (): void {
    $response = LoggingTestServer::tool(StructuredLogTool::class);

    $response->assertLogCount(2)
        ->assertLogSent(LogLevel::Error)
        ->assertLogSent(LogLevel::Info);
});

it('supports string and array data in logs', function (): void {
    $response = LoggingTestServer::tool(StructuredLogTool::class);

    // Just verify both logs were sent
    $response->assertSentNotification('notifications/message')
        ->assertLogCount(2);
});
