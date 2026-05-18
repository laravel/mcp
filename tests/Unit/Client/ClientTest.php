<?php

declare(strict_types=1);

use Laravel\Mcp\Client\Client;
use Laravel\Mcp\Client\Exceptions\ClientException;
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
    expect($client->connected)->toBeTrue();
    expect($client->protocolVersion)->toBe(ProtocolVersion::LATEST->value);
    expect($client->serverInfo?->name)->toBe('Test Server');
    expect($client->serverInfo?->version)->toBe('1.0.0');
    expect($client->serverCapabilities)->toBeInstanceOf(stdClass::class);
    expect($client->serverInfo)->not->toBeNull();
    expect($client->instructions)->toBeNull();

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

    expect($client->connected)->toBeFalse();

    $client->ping();

    expect($client->connected)->toBeTrue();
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
    expect($client->connected)->toBeFalse();
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

it('times out when only notification frames are received', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->repeatResponse = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'notifications/message',
        'params' => ['level' => 'info', 'data' => 'still working'],
    ]);

    $client = new Client($transport, 0.01);
    $client->connect();

    expect(function () use ($client): void {
        $client->ping();
    })
        ->toThrow(ClientException::class, 'Timed out while waiting for server response.');
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
    expect($client->connected)->toBeFalse();
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

it('throws when the initialize result is invalid', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => 'invalid',
            'capabilities' => new stdClass,
            'serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
        ],
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'Invalid initialize response from server.');
});

it('provides a local static factory', function (): void {
    $client = Client::local('php', ['-v']);

    expect($client)->toBeInstanceOf(Client::class);
    expect($client->connected)->toBeFalse();
});

it('can ping a registered Laravel MCP stdio server', function (): void {
    $client = Client::local(PHP_BINARY, [__DIR__.'/../../../vendor/bin/testbench', 'mcp:start', 'test-mcp']);

    $client->ping();

    expect($client->serverInfo?->name)->toBe('Laravel MCP Server');

    $client->disconnect();
});

it('responds to server-initiated ping requests with an empty result', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 'server-ping-1',
        'method' => 'ping',
    ]);
    $transport->responses[] = pingResponse(2);

    $client = new Client($transport);
    $client->ping();

    $pingReply = json_decode($transport->sent[3], true);
    expect($pingReply['id'])->toBe('server-ping-1');
    expect($pingReply['result'])->toBeArray()->toBeEmpty();
    expect($pingReply)->not->toHaveKey('error');
});

it('stores the full server info and instructions from initialize', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => ProtocolVersion::LATEST->value,
            'capabilities' => new stdClass,
            'serverInfo' => [
                'name' => 'ExampleServer',
                'title' => 'Example Server Display Name',
                'version' => '1.0.0',
                'description' => 'An example MCP server providing tools and resources',
                'icons' => [['src' => 'https://example.com/server-icon.svg', 'mimeType' => 'image/svg+xml']],
                'websiteUrl' => 'https://example.com/server',
            ],
            'instructions' => 'Optional instructions for the client',
        ],
    ]);

    $client = new Client($transport);
    $client->connect();

    $info = $client->serverInfo;
    expect($info)->not->toBeNull();
    expect($info->name)->toBe('ExampleServer');
    expect($info->title)->toBe('Example Server Display Name');
    expect($info->version)->toBe('1.0.0');
    expect($info->description)->toBe('An example MCP server providing tools and resources');
    expect($info->icons)->toHaveCount(1);
    expect($info->websiteUrl)->toBe('https://example.com/server');
    expect($client->instructions)->toBe('Optional instructions for the client');
});

it('times out when a stdio process stays silent', function (): void {
    $client = Client::local(PHP_BINARY, ['-r', 'sleep(1);'], 0.05);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'Timed out while waiting for server response.');
});
