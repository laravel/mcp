<?php

use Laravel\Mcp\Client\Exceptions\ConnectionException;
use Laravel\Mcp\Client\Transport\StdioClientTransport;

it('reports not connected before connect', function (): void {
    $transport = new StdioClientTransport('php', ['-r', 'echo "ok";']);

    expect($transport->isConnected())->toBeFalse();
});

it('throws when sending while not connected', function (): void {
    $transport = new StdioClientTransport('php');

    $transport->send('test');
})->throws(ConnectionException::class, 'Not connected to process.');

it('throws when notifying while not connected', function (): void {
    $transport = new StdioClientTransport('php');

    $transport->notify('test');
})->throws(ConnectionException::class, 'Not connected to process.');

it('detects disconnected process', function (): void {
    $transport = new StdioClientTransport('php', ['-r', 'exit(0);']);

    $transport->connect();

    usleep(100000);

    expect($transport->isConnected())->toBeFalse();

    $transport->disconnect();
});

it('can connect to a simple process and communicate', function (): void {
    $transport = new StdioClientTransport('php', ['-r', 'while ($line = fgets(STDIN)) { echo trim($line) . "\n"; }']);

    $transport->connect();

    expect($transport->isConnected())->toBeTrue();

    $response = $transport->send('hello');
    expect($response)->toBe('hello');

    $transport->disconnect();
    expect($transport->isConnected())->toBeFalse();
});

it('can send notifications without reading response', function (): void {
    $transport = new StdioClientTransport('php', ['-r', 'while ($line = fgets(STDIN)) { echo trim($line) . "\n"; }']);

    $transport->connect();

    $transport->notify('{"jsonrpc":"2.0","method":"notifications/initialized"}');

    expect($transport->isConnected())->toBeTrue();

    $transport->disconnect();
});

it('disconnect is idempotent', function (): void {
    $transport = new StdioClientTransport('php', ['-r', 'echo "ok";']);

    $transport->disconnect();
    $transport->disconnect();

    expect($transport->isConnected())->toBeFalse();
});
