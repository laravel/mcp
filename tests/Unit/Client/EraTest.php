<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Enums\ProtocolEra;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Tests\Fixtures\Client\FakeTransport;

it('discovers a modern server without a handshake', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();

    $client = new Client($transport);
    $client->connect();

    expect($client->era())->toBe(ProtocolEra::MODERN);
    expect($client->initializeResult())->toBeNull();
    expect($client->discoverResult()?->supportedVersions)->toBe([ProtocolVersion::LATEST->value]);
    expect($client->discoverResult()?->serverInfo?->name)->toBe('Test Server');
    expect($client->discoverResult()?->instructions)->toBe('Be nice.');

    $discover = json_decode($transport->sent[0], true);

    expect($transport->sent)->toHaveCount(1);
    expect($discover['method'])->toBe('server/discover');
    expect($transport->protocolVersion)->toBe(ProtocolVersion::LATEST->value);
});

it('sends the required protocol metadata on every modern request', function (): void {
    config(['app.name' => 'Acme MCP App']);

    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['tools' => [['name' => 'add', 'description' => 'Adds two numbers']]],
    ]);

    (new Client($transport))->tools();

    foreach ($transport->sent as $raw) {
        $meta = json_decode($raw, true)['params']['_meta'];

        expect($meta['io.modelcontextprotocol/protocolVersion'])->toBe(ProtocolVersion::LATEST->value);
        expect($meta)->toHaveKey('io.modelcontextprotocol/clientCapabilities');
        expect($meta['io.modelcontextprotocol/clientInfo']['name'])->toBe('Acme MCP App');
    }
});

it('falls back to the handshake when discover is not implemented', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = initializeResponse(2);

    $client = new Client($transport);
    $client->connect();

    expect($client->era())->toBe(ProtocolEra::LEGACY);
    expect($client->initializeResult()?->protocolVersion)->toBe(ProtocolVersion::V2025_11_25->value);

    expect(json_decode($transport->sent[0], true)['method'])->toBe('server/discover');
    expect(json_decode($transport->sent[1], true)['method'])->toBe('initialize');
    expect(json_decode($transport->sent[2], true)['method'])->toBe('notifications/initialized');
});

it('never sends the modern version through the legacy handshake', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = initializeResponse(2);

    (new Client($transport))->connect();

    $initialize = json_decode($transport->sent[1], true);

    expect($initialize['params']['protocolVersion'])->toBe(ProtocolVersion::V2025_11_25->value);
    expect($initialize['params'])->not->toHaveKey('_meta');
});

it('does not fall back when the server answers with a modern protocol error', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => [
            'code' => -32022,
            'message' => 'Unsupported protocol version',
            'data' => ['supported' => ['2027-01-01'], 'requested' => '2026-07-28'],
        ],
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(JsonRpcException::class, 'Unsupported protocol version');

    expect($transport->sent)->toHaveCount(1);
});

it('skips the probe when the legacy era is requested', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    $client = (new Client($transport))->withEra(ProtocolEra::LEGACY);
    $client->connect();

    expect($client->era())->toBe(ProtocolEra::LEGACY);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('initialize');
});

it('does not fall back when the modern era is requested', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();

    $client = (new Client($transport))->withEra(ProtocolEra::MODERN);

    expect(fn (): Client => $client->connect())->toThrow(JsonRpcException::class);

    expect($transport->sent)->toHaveCount(1);
});

it('remembers a detected legacy server across reconnects', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = initializeResponse(2);

    $client = new Client($transport);
    $client->connect();
    $client->disconnect();

    $transport->sent = [];
    $transport->responses[] = initializeResponse(3);

    $client->connect();

    expect($client->era())->toBe(ProtocolEra::LEGACY);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('initialize');
});

it('reconnects when the era is forced after connecting', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = initializeResponse(2);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => []],
    ]);

    $client = new Client($transport);
    $client->connect();

    expect($client->era())->toBe(ProtocolEra::MODERN);

    $client->withEra(ProtocolEra::LEGACY);
    $client->tools();

    expect($client->era())->toBe(ProtocolEra::LEGACY);

    $handshake = json_decode($transport->sent[1], true);

    expect($handshake['method'])->toBe('initialize');
    expect(json_decode($transport->sent[3], true)['params'] ?? null)->not->toHaveKey('_meta');
});

it('rejects a modern server that does not support the latest version', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'supportedVersions' => ['2027-01-01'],
            'capabilities' => [],
            'instructions' => null,
        ],
    ]);

    expect(function () use ($transport): void {
        (new Client($transport))->connect();
    })
        ->toThrow(ClientException::class, 'The server does not support protocol version [2026-07-28]. It supports [2027-01-01].');
});
