<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Client;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Tests\Fixtures\Client\FakeTransport;

function initializeResponse(): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new stdClass,
            'serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
        ],
    ]);
}

function pingResponse(int $id): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => new stdClass,
    ]);
}

it('performs the initialize handshake on connect', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    $client = new Client($transport);
    $client->connect();

    expect($transport->connected)->toBeTrue();
    expect($client->isConnected())->toBeTrue();

    $initialize = json_decode($transport->sent[0], true);
    expect($initialize['method'])->toBe('initialize');
    expect($initialize['params']['protocolVersion'])->toBe(ProtocolVersion::LATEST->value);
    expect($initialize['params']['clientInfo']['name'])->toBe('Laravel MCP Client');

    $initialized = json_decode($transport->sent[1], true);
    expect($initialized['method'])->toBe('notifications/initialized');
    expect($initialized)->not->toHaveKey('id');
});

it('pings the server', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = pingResponse(2);

    $client = new Client($transport);
    $client->ping();

    $ping = json_decode($transport->sent[2], true);
    expect($ping['method'])->toBe('ping');
    expect($ping['id'])->toBe(2);
});

it('lazily connects when ping is called first', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = pingResponse(2);

    $client = new Client($transport);

    expect($client->isConnected())->toBeFalse();

    $client->ping();

    expect($client->isConnected())->toBeTrue();
});

it('does not reconnect when already connected', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    $client = new Client($transport);
    $client->connect();
    $client->connect();

    expect(count($transport->sent))->toBe(2);
});

it('disconnects cleanly', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    $client = new Client($transport);
    $client->connect();
    $client->disconnect();

    expect($transport->connected)->toBeFalse();
    expect($client->isConnected())->toBeFalse();
});

it('skips notification frames received before the matching response', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'notifications/message',
        'params' => ['level' => 'info', 'data' => 'hello'],
    ]);
    $transport->responses[] = pingResponse(2);

    $client = new Client($transport);
    $client->ping();

    expect($transport->responses)->toBeEmpty();
});

it('disconnects the transport when the initialize handshake fails', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => ['code' => -32600, 'message' => 'Invalid request'],
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(JsonRpcException::class);

    expect($transport->connected)->toBeFalse();
    expect($client->isConnected())->toBeFalse();
});

it('throws when the server returns a JSON-RPC error', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => ['code' => -32600, 'message' => 'Invalid request'],
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(JsonRpcException::class, 'Invalid request');
});

it('provides a stdio static factory', function (): void {
    $client = Client::stdio('php', ['-v']);

    expect($client)->toBeInstanceOf(Client::class);
    expect($client->isConnected())->toBeFalse();
});
