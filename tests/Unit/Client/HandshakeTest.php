<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Tests\Fixtures\Client\FakeTransport;

function negotiatingTransport(): FakeTransport
{
    $transport = new FakeTransport;
    $transport->negotiates = true;

    return $transport;
}

it('probes discovery first and stays modern against a modern server', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = discoverResponse();

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect($client->initializeResult())->toBeNull();
    expect($client->discoverResult()?->supportedVersions)->toBe([ProtocolVersion::LATEST->value]);
    expect($client->discoverResult()?->serverInfo?->name)->toBe('Test Server');
    expect($client->discoverResult()?->instructions)->toBe('Be nice.');

    expect($transport->sent)->toHaveCount(1);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('server/discover');
    expect($transport->protocolVersion)->toBe(ProtocolVersion::LATEST);
});

it('falls back to the handshake when discovery is not implemented', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = initializeResponse(2);

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
    expect($client->discoverResult())->toBeNull();
    expect($client->initializeResult()?->serverInfo->name)->toBe('Test Server');

    expect($transport->sent)->toHaveCount(3);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('server/discover');
    expect(json_decode($transport->sent[1], true)['method'])->toBe('initialize');
    expect(json_decode($transport->sent[2], true)['method'])->toBe('notifications/initialized');
    expect($transport->protocolVersion)->toBe(ProtocolVersion::V2025_11_25);
});

it('falls back to the handshake when the discovery probe times out', function (): void {
    $transport = negotiatingTransport();
    $transport->timesOutOnDiscover = true;
    $transport->responses[] = initializeResponse();

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
    expect($client->discoverResult())->toBeNull();
    expect($transport->connected)->toBeTrue();

    expect($transport->sent)->toHaveCount(2);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('initialize');
    expect(json_decode($transport->sent[1], true)['method'])->toBe('notifications/initialized');
});

it('never sends a discovery era version or metadata through the handshake', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = initializeResponse(2);

    (new Client($transport))->connect();

    $initialize = json_decode($transport->sent[1], true);

    expect($initialize['params']['protocolVersion'])->toBe(ProtocolVersion::V2025_11_25->value);
    expect($initialize['params'])->not->toHaveKey('_meta');
});

it('does not fall back when the probe fails for a reason other than a missing method', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => ['code' => -32603, 'message' => 'Internal error'],
    ]);

    expect(fn (): Client => (new Client($transport))->connect())
        ->toThrow(JsonRpcException::class, 'Internal error');

    expect($transport->sent)->toHaveCount(1);
});

it('rejects a server that will not settle on the requested version', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = discoverResponse(1, ['2025-11-25']);
    $transport->responses[] = initializeResponse(2);

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'The server settled on protocol version [2025-11-25] while [2026-07-28] was requested.');

    expect($transport->sent)->toHaveCount(1);
});

it('does not fall back when the error identifies a modern server', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => ['code' => -32020, 'message' => 'Header mismatch: The [Mcp-Method] header is required.'],
    ]);

    expect(fn (): Client => (new Client($transport))->connect())
        ->toThrow(JsonRpcException::class, 'Header mismatch');

    expect($transport->sent)->toHaveCount(1);
});

it('retries with a mutually supported version from an unsupported version error', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => [
            'code' => -32022,
            'message' => 'Unsupported protocol version',
            'data' => ['supported' => ['2025-11-25', '2025-06-18'], 'requested' => '2026-07-28'],
        ],
    ]);
    $transport->responses[] = initializeResponse(2);

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
    expect(json_decode($transport->sent[1], true)['params']['protocolVersion'])
        ->toBe(ProtocolVersion::V2025_11_25->value);
});

it('propagates an unsupported version error with no mutually supported version', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => [
            'code' => -32022,
            'message' => 'Unsupported protocol version',
            'data' => ['supported' => ['2027-01-01'], 'requested' => '2026-07-28'],
        ],
    ]);

    expect(fn (): Client => (new Client($transport))->connect())
        ->toThrow(JsonRpcException::class, 'Unsupported protocol version');

    expect($transport->sent)->toHaveCount(1);
});

it('falls back to the handshake when discovery lists only a legacy version', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = discoverResponse(1, ['2025-11-25']);
    $transport->responses[] = initializeResponse(2);

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
    expect($client->discoverResult())->toBeNull();
    expect(json_decode($transport->sent[1], true)['method'])->toBe('initialize');
    expect(json_decode($transport->sent[1], true)['params'])->not->toHaveKey('_meta');
});

it('surfaces the handshake failure and not the probe when the fallback fails', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'error' => ['code' => -32603, 'message' => 'Internal error'],
    ]);

    expect(fn (): Client => (new Client($transport))->connect())
        ->toThrow(JsonRpcException::class, 'Internal error');
});

it('sends the required protocol metadata on every discovery era request', function (): void {
    config(['app.name' => 'Acme MCP App']);

    $transport = negotiatingTransport();
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

it('skips the probe when a discovery version is pinned', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = discoverResponse();

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect($transport->sent)->toHaveCount(1);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('server/discover');
});

it('offers the pinned legacy version and does not probe discovery', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = initializeResponse(1, ProtocolVersion::V2025_06_18->value);

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::V2025_06_18);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_06_18);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('initialize');
    expect(json_decode($transport->sent[0], true)['params']['protocolVersion'])
        ->toBe(ProtocolVersion::V2025_06_18->value);
});

it('reports the pinned version before connecting', function (): void {
    $client = (new Client(negotiatingTransport()))->withProtocolVersion(ProtocolVersion::LATEST);

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect($client->connected())->toBeFalse();
});

it('rejects a protocol version this client does not support', function (): void {
    $client = new Client(new FakeTransport);

    expect(fn (): Client => $client->withProtocolVersion(ProtocolVersion::V2024_11_05))
        ->toThrow(ClientException::class, 'This client does not support protocol version [2024-11-05].');
});

it('remembers a discovery era server across reconnects', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = discoverResponse();

    $client = new Client($transport);
    $client->connect();
    $client->disconnect();

    $transport->sent = [];
    $transport->responses[] = discoverResponse(2);

    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect($transport->sent)->toHaveCount(1);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('server/discover');
});

it('remembers a legacy server across reconnects', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = initializeResponse(2);

    $client = new Client($transport);
    $client->connect();
    $client->disconnect();

    $transport->sent = [];
    $transport->responses[] = initializeResponse(3);

    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
    expect($transport->sent)->toHaveCount(2);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('initialize');
});

it('re-probes within the same reconnect when a remembered discovery era server turns legacy', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = discoverResponse();

    $client = new Client($transport);
    $client->connect();
    $client->disconnect();

    $transport->sent = [];
    $transport->responses[] = methodNotFoundResponse(2);
    $transport->responses[] = initializeResponse(3);

    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('server/discover');
    expect(json_decode($transport->sent[1], true)['method'])->toBe('initialize');
});

it('re-probes within the same reconnect when a remembered legacy server turns modern', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = initializeResponse(2);

    $client = new Client($transport);
    $client->connect();
    $client->disconnect();

    $transport->sent = [];
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'error' => ['code' => -32601, 'message' => 'Method not found.'],
    ]);
    $transport->responses[] = discoverResponse(4);

    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect(json_decode($transport->sent[0], true)['method'])->toBe('initialize');
    expect(json_decode($transport->sent[1], true)['method'])->toBe('server/discover');
});

it('reconnects when the protocol version is pinned after connecting', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = methodNotFoundResponse();
    $transport->responses[] = initializeResponse(2);
    $transport->responses[] = discoverResponse(3);
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 4,
        'result' => ['tools' => []],
    ]);

    $client = new Client($transport);
    $client->connect();

    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);

    $client->withProtocolVersion(ProtocolVersion::LATEST);
    $client->tools();

    expect($client->protocolVersion())->toBe(ProtocolVersion::LATEST);
    expect(json_decode($transport->sent[3], true)['method'])->toBe('server/discover');
    expect(json_decode($transport->sent[4], true)['params'])->toHaveKey('_meta');
});

it('rejects a malformed discover response', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['capabilities' => []],
    ]);

    expect(function () use ($transport): void {
        (new Client($transport))->connect();
    })->toThrow(ClientException::class, 'Invalid discover response from server.');
});

it('rejects a server with no protocol version this client can speak', function (): void {
    $transport = negotiatingTransport();
    $transport->responses[] = discoverResponse(1, ['2027-01-01']);

    expect(function () use ($transport): void {
        (new Client($transport))->connect();
    })
        ->toThrow(ClientException::class, 'The server supports protocol versions [2027-01-01]. This client supports [2026-07-28, 2025-11-25, 2025-06-18].');
});
