<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\LogLevel;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Support\LoggingManager;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    LoggingManager::setDefaultLevel(LogLevel::Debug);
});

class LogAssertServer extends Server
{
    protected array $tools = [
        MultiLevelLogTool::class,
        SingleLogTool::class,
        NoLogTool::class,
        ArrayDataLogTool::class,
    ];
}

class MultiLevelLogTool extends Tool
{
    public function handle(Request $request): Generator
    {
        yield Response::log(LogLevel::Error, 'Error occurred');
        yield Response::log(LogLevel::Warning, 'Warning message');
        yield Response::log(LogLevel::Info, 'Info message');

        yield Response::text('Done');
    }
}

class SingleLogTool extends Tool
{
    public function handle(Request $request): Generator
    {
        yield Response::log(LogLevel::Error, 'Single error log');

        yield Response::text('Complete');
    }
}

class NoLogTool extends Tool
{
    public function handle(Request $request): string
    {
        return 'No logs here';
    }
}

class ArrayDataLogTool extends Tool
{
    public function handle(Request $request): Generator
    {
        yield Response::log(
            LogLevel::Error,
            ['error' => 'Connection failed', 'host' => 'localhost', 'port' => 5432]
        );

        yield Response::text('Done');
    }
}

it('asserts log was sent with a specific level', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogSent(LogLevel::Error);
    $response->assertLogSent(LogLevel::Warning);
    $response->assertLogSent(LogLevel::Info);
});

it('asserts log was sent with level and message content', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogSent(LogLevel::Error, 'Error occurred')
        ->assertLogSent(LogLevel::Warning, 'Warning message')
        ->assertLogSent(LogLevel::Info, 'Info message');
});

it('fails when asserting log sent with wrong level', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogSent(LogLevel::Debug);
})->throws(AssertionFailedError::class);

it('fails when asserting log sent with wrong message content', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogSent(LogLevel::Error, 'Wrong message');
})->throws(AssertionFailedError::class);

it('asserts log was not sent', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogNotSent(LogLevel::Debug)
        ->assertLogNotSent(LogLevel::Emergency);
});

it('fails when asserting log not sent but it was', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogNotSent(LogLevel::Error);
})->throws(AssertionFailedError::class);

it('asserts correct log count', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogCount(3);
});

it('fails when asserting wrong log count', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogCount(5);
})->throws(ExpectationFailedException::class);

it('asserts zero log count when no logs sent', function (): void {
    $response = LogAssertServer::tool(NoLogTool::class);

    $response->assertLogCount(0);
});

it('asserts single log count', function (): void {
    $response = LogAssertServer::tool(SingleLogTool::class);

    $response->assertLogCount(1)
        ->assertLogSent(LogLevel::Error, 'Single error log');
});

it('asserts log sent with array data containing substring', function (): void {
    $response = LogAssertServer::tool(ArrayDataLogTool::class);

    $response->assertLogSent(LogLevel::Error, 'Connection failed')
        ->assertLogSent(LogLevel::Error, 'localhost');
});

it('chains multiple log assertions', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertLogCount(3)
        ->assertLogSent(LogLevel::Error)
        ->assertLogSent(LogLevel::Warning)
        ->assertLogSent(LogLevel::Info)
        ->assertLogNotSent(LogLevel::Debug)
        ->assertLogNotSent(LogLevel::Emergency);
});

it('can combine log assertions with other assertions', function (): void {
    $response = LogAssertServer::tool(MultiLevelLogTool::class);

    $response->assertSee('Done')
        ->assertLogCount(3)
        ->assertLogSent(LogLevel::Error);
});
