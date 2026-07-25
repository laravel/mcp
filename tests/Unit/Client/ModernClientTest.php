<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Tests\Fixtures\Client\FakeTransport;

function discoverResponse(array $overrides = []): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => 'discover-1',
        'result' => array_merge([
            'resultType' => 'complete',
            'supportedVersions' => ProtocolVersion::supported(),
            'capabilities' => ['tools' => (object) []],
            'instructions' => 'Test instructions.',
            'ttlMs' => 0,
            'cacheScope' => 'private',
            '_meta' => [
                'io.modelcontextprotocol/serverInfo' => ['name' => 'Modern Server', 'version' => '2.0.0'],
            ],
        ], $overrides),
    ]);
}

it('negotiates the modern revision through server/discover without a handshake', function (): void {
    $transport = new FakeTransport;
    $transport->discoverResponse = discoverResponse();

    $client = new Client($transport);
    $client->connect();

    expect($client->initializeResult()?->protocolVersion)->toBe(ProtocolVersion::LATEST->value)
        ->and($client->initializeResult()?->serverInfo->name)->toBe('Modern Server')
        ->and($client->initializeResult()?->instructions)->toBe('Test instructions.');

    $methods = array_map(
        fn (string $message): mixed => json_decode($message, true)['method'] ?? null,
        $transport->sent,
    );

    expect($methods)->toBe(['server/discover'])
        ->and($transport->protocolVersion)->toBe(ProtocolVersion::LATEST->value);
});

it('stamps protocol metadata into _meta on every modern request', function (): void {
    $transport = new FakeTransport;
    $transport->discoverResponse = discoverResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['resultType' => 'complete', 'tools' => []],
    ]);

    (new Client($transport))->tools();

    $listRequest = json_decode(end($transport->sent), true);
    $meta = $listRequest['params']['_meta'];

    expect($listRequest['method'])->toBe('tools/list')
        ->and($meta['io.modelcontextprotocol/protocolVersion'])->toBe(ProtocolVersion::LATEST->value)
        ->and($meta['io.modelcontextprotocol/clientInfo'])->toHaveKeys(['name', 'version'])
        ->and($meta)->toHaveKey('io.modelcontextprotocol/clientCapabilities');
});

it('falls back to the legacy handshake when discover reports no mutual modern version', function (): void {
    $transport = new FakeTransport;
    $transport->discoverResponse = discoverResponse(['supportedVersions' => ['2099-01-01']]);
    $transport->responses[] = initializeResponse();

    $client = new Client($transport);
    $client->connect();

    expect($client->initializeResult()?->protocolVersion)->toBe(ProtocolVersion::LATEST_LEGACY->value);

    $methods = array_map(
        fn (string $message): mixed => json_decode($message, true)['method'] ?? null,
        $transport->sent,
    );

    expect($methods)->toContain('initialize')
        ->and($methods)->toContain('notifications/initialized');
});

it('skips the discover probe on reconnect once the server era is known to be legacy', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new stdClass,
            'serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
        ],
    ]);

    $client = new Client($transport);
    $client->connect();
    $client->disconnect();
    $client->connect();

    $probes = array_filter(
        $transport->sent,
        fn (string $message): bool => (json_decode($message, true)['method'] ?? null) === 'server/discover',
    );

    expect($probes)->toHaveCount(1);
});

it('throws when a modern server returns an input_required result', function (): void {
    $transport = new FakeTransport;
    $transport->discoverResponse = discoverResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'resultType' => 'input_required',
            'inputRequests' => [],
            'requestState' => 'opaque',
        ],
    ]);

    expect(fn () => (new Client($transport))->tools())
        ->toThrow(ClientException::class, 'input_required');
});
