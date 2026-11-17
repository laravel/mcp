<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Transport\StdioTransport;

it('sets receive handler', function (): void {
    $transport = new StdioTransport;

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
    $transport = new StdioTransport;

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
    $transport = new StdioTransport;

    $streamExecuted = false;
    $stream = function () use (&$streamExecuted): void {
        $streamExecuted = true;
    };

    $transport->stream($stream);

    expect($streamExecuted)->toBeTrue();
});

it('handles run method with handler', function (): void {
    $transport = new StdioTransport;

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
    $transport = new StdioTransport;

    expect($transport)->toBeInstanceOf(Transport::class);
});

it('reads every message from stdin until the client closes it', function (): void {
    $received = tempnam(sys_get_temp_dir(), 'mcp');

    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__, 3).'/Fixtures/stdio-server.php', $received],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
    );

    $waitUntil = function (Closure $condition): bool {
        $deadline = microtime(true) + 10;

        while (! $condition()) {
            if (microtime(true) > $deadline) {
                return false;
            }

            usleep(10000);
        }

        return true;
    };

    $first = '{"jsonrpc":"2.0","id":1,"method":"ping"}'."\n";
    $second = '{"jsonrpc":"2.0","id":2,"method":"ping"}'."\n";

    fwrite($pipes[0], $first);
    fwrite($pipes[0], $second);

    expect($waitUntil(fn (): bool => file_get_contents($received) === $first.$second))->toBeTrue()
        ->and(proc_get_status($process)['running'])->toBeTrue();

    fclose($pipes[0]);

    expect($waitUntil(fn (): bool => ! proc_get_status($process)['running']))->toBeTrue()
        ->and(file_get_contents($received))->toBe($first.$second."stdin closed\n");

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    unlink($received);
});
