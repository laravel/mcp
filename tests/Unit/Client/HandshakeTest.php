<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Tests\Fixtures\Client\FakeTransport;

it('opens with the handshake and does not probe a legacy server', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
    expect($client->discoverResult())->toBeNull();
    expect($client->initializeResult()?->serverInfo->name)->toBe('Test Server');

    expect($transport->sent)->toHaveCount(2);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('initialize');
    expect(json_decode($transport->sent[1], true)['method'])->toBe('notifications/initialized');
});

it('never sends a discovery era version through the handshake', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    (new Client($transport))->connect();

    $initialize = json_decode($transport->sent[0], true);

    expect($initialize['params']['protocolVersion'])->toBe(ProtocolVersion::V2025_11_25->value);
    expect($initialize['params'])->not->toHaveKey('_meta');
});

it('falls back to discovery when the handshake is not implemented', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = discoverResponse(2);

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect($client->initializeResult())->toBeNull();
    expect($client->discoverResult()?->supportedVersions)->toBe([ProtocolVersion::LATEST->value]);
    expect($client->discoverResult()?->serverInfo?->name)->toBe('Test Server');
    expect($client->discoverResult()?->instructions)->toBe('Be nice.');

    expect($transport->sent)->toHaveCount(2);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('initialize');
    expect(json_decode($transport->sent[1], true)['method'])->toBe('server/discover');
    expect($transport->protocolVersion)->toBe(ProtocolVersion::LATEST->value);
});

it('falls back to discovery when the server demands the mirrored headers', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => ['code' => -32020, 'message' => 'Header mismatch: The [Mcp-Method] header is required.'],
    ]);
    $transport->responses[] = discoverResponse(2);

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect(json_decode($transport->sent[1], true)['method'])->toBe('server/discover');
});

it('propagates a handshake error that does not point at discovery', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => ['code' => -32602, 'message' => 'Invalid params'],
    ]);

    expect(fn (): Client => (new Client($transport))->connect())
        ->toThrow(JsonRpcException::class, 'Invalid params');

    expect($transport->sent)->toHaveCount(1);
});

it('sends the required protocol metadata on every discovery era request', function (): void {
    config(['app.name' => 'Acme MCP App']);

    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = discoverResponse(2);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => [['name' => 'add', 'description' => 'Adds two numbers']]],
    ]);

    (new Client($transport))->tools();

    expect(json_decode($transport->sent[0], true)['params'])->not->toHaveKey('_meta');

    foreach (array_slice($transport->sent, 1) as $raw) {
        $meta = json_decode($raw, true)['params']['_meta'];

        expect($meta['io.modelcontextprotocol/protocolVersion'])->toBe(ProtocolVersion::LATEST->value);
        expect($meta)->toHaveKey('io.modelcontextprotocol/clientCapabilities');
        expect($meta['io.modelcontextprotocol/clientInfo']['name'])->toBe('Acme MCP App');
    }
});

it('skips the handshake when a discovery version is pinned', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = discoverResponse();

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect($transport->sent)->toHaveCount(1);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('server/discover');
});

it('offers the pinned version and does not fall back to discovery', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::V2025_06_18);

    expect(fn (): Client => $client->connect())->toThrow(JsonRpcException::class);

    expect($transport->sent)->toHaveCount(1);
    expect(json_decode($transport->sent[0], true)['params']['protocolVersion'])
        ->toBe(ProtocolVersion::V2025_06_18->value);
});

it('rejects a protocol version this client does not support', function (): void {
    $client = new Client(new FakeTransport);

    expect(fn (): Client => $client->withProtocolVersion(ProtocolVersion::V2024_11_05))
        ->toThrow(ClientException::class, 'This client does not support protocol version [2024-11-05].');
});

it('remembers a discovery era server across reconnects', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = discoverResponse(2);

    $client = new Client($transport);
    $client->connect();
    $client->disconnect();

    $transport->sent = [];
    $transport->responses[] = discoverResponse(3);

    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect($transport->sent)->toHaveCount(1);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('server/discover');
});

it('reconnects when the protocol version is pinned after connecting', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = discoverResponse(2);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'result' => ['tools' => []],
    ]);

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);

    $client->withProtocolVersion(ProtocolVersion::LATEST);
    $client->tools();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect(json_decode($transport->sent[2], true)['method'])->toBe('server/discover');
    expect(json_decode($transport->sent[3], true)['params'])->toHaveKey('_meta');
});

it('rejects a malformed discover response', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => ['capabilities' => []],
    ]);

    expect(function () use ($transport): void {
        (new Client($transport))->connect();
    })->toThrow(ClientException::class, 'Invalid discover response from server.');
});

it('rejects a discovery era server that does not support the latest version', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
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
