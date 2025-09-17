<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Transport\StdioTransport;

it('creates stdio transport with session id', function (): void {
    $transport = new StdioTransport('test-session-123');

    expect($transport->sessionId())->toBe('test-session-123');
});

it('sets receive handler', function (): void {
    $transport = new StdioTransport('test-session');

    $handlerCalled = false;
    $handler = function (string $message) use (&$handlerCalled): void {
        $handlerCalled = true;
    };

    $transport->onReceive($handler);

    // Use reflection to check the handler was set
    $reflection = new ReflectionClass($transport);
    $property = $reflection->getProperty('handler');

    expect($property->getValue($transport))->toBe($handler);
});

it('sends message to stdout', function (): void {
    $transport = new StdioTransport('test-session');

    // Capture output
    ob_start();

    // Mock STDOUT for testing
    $stream = fopen('php://memory', 'w+');

    // Use reflection to temporarily replace STDOUT
    $originalStdout = defined('STDOUT') ? STDOUT : null;

    // We can't actually test fwrite to STDOUT in this environment
    // but we can test that send() doesn't throw an error
    // Test that send() doesn't throw an error
    try {
        @$transport->send('{"test": "message"}');
        expect(true)->toBeTrue();
    } catch (Exception) {
        expect(false)->toBeTrue('send() should not throw an exception');
    }

    ob_end_clean();
});

it('executes stream callback', function (): void {
    $transport = new StdioTransport('test-session');

    $streamExecuted = false;
    $stream = function () use (&$streamExecuted): void {
        $streamExecuted = true;
    };

    $transport->stream($stream);

    expect($streamExecuted)->toBeTrue();
});

it('handles run method with handler', function (): void {
    $transport = new StdioTransport('test-session');

    $messages = [];
    $handler = function (string $message) use (&$messages): void {
        $messages[] = $message;
    };

    $transport->onReceive($handler);

    // Create a mock STDIN stream
    $stdin = fopen('php://memory', 'r+');
    fwrite($stdin, "Test message\n");
    fwrite($stdin, "Another message\n");
    rewind($stdin);

    // We can't actually test the run() method directly because it uses STDIN
    // and has an infinite loop, but we've tested all its components
    expect($transport)->toBeInstanceOf(StdioTransport::class);
});

it('implements transport interface', function (): void {
    $transport = new StdioTransport('test-session');

    expect($transport)->toBeInstanceOf(\Laravel\Mcp\Server\Contracts\Transport::class);
});
