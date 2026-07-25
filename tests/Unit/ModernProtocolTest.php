<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;

function modernMeta(string $version = '2026-07-28'): array
{
    return [
        MetaKey::PROTOCOL_VERSION->value => $version,
        MetaKey::CLIENT_INFO->value => ['name' => 'Pest', 'version' => '1.0.0'],
        MetaKey::CLIENT_CAPABILITIES->value => (object) [],
    ];
}

function modernMessage(string $method, array $params = [], string $version = '2026-07-28'): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => $method,
        'params' => array_merge($params, ['_meta' => modernMeta($version)]),
    ]);
}

function handleModern(string $payload): array
{
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);
    $server->start();

    ($transport->handler)($payload);

    return json_decode((string) $transport->sent[0], true);
}

it('serves stateless requests without an initialize handshake', function (): void {
    $response = handleModern(modernMessage('tools/list'));

    expect($response['result']['resultType'])->toBe('complete')
        ->and($response['result']['tools'])->not->toBeEmpty()
        ->and($response['result']['_meta'][MetaKey::SERVER_INFO->value]['name'])->not->toBeEmpty();
});

it('stamps cacheable results with ttlMs and cacheScope for modern requests', function (): void {
    $response = handleModern(modernMessage('tools/list'));

    expect($response['result']['ttlMs'])->toBe(0)
        ->and($response['result']['cacheScope'])->toBe('private');
});

it('does not stamp modern fields on non-cacheable modern results', function (): void {
    $response = handleModern(modernMessage('tools/call', [
        'name' => 'say-hi-tool',
        'arguments' => ['name' => 'John'],
    ]));

    expect($response['result'])->not->toHaveKey('ttlMs')
        ->and($response['result'])->not->toHaveKey('cacheScope')
        ->and($response['result']['resultType'])->toBe('complete');
});

it('does not decorate legacy results with modern fields', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);
    $server->start();

    ($transport->handler)(json_encode(listToolsMessage()));

    $response = json_decode((string) $transport->sent[0], true);

    expect($response['result'])->not->toHaveKey('resultType')
        ->and($response['result'])->not->toHaveKey('ttlMs')
        ->and($response['result'])->not->toHaveKey('_meta');
});

it('implements server/discover', function (): void {
    $response = handleModern(modernMessage('server/discover'));

    expect($response['result']['supportedVersions'])->toBe(ProtocolVersion::supported())
        ->and($response['result']['capabilities'])->toHaveKeys(['tools', 'resources', 'prompts'])
        ->and($response['result']['instructions'])->toBeString()
        ->and($response['result']['resultType'])->toBe('complete')
        ->and($response['result']['ttlMs'])->toBe(0)
        ->and($response['result']['_meta'][MetaKey::SERVER_INFO->value])->toHaveKeys(['name', 'version']);
});

it('rejects requests declaring an unsupported protocol version', function (): void {
    $response = handleModern(modernMessage('tools/list', version: '1900-01-01'));

    expect($response['error']['code'])->toBe(-32022)
        ->and($response['error']['data']['supported'])->toBe(ProtocolVersion::supported())
        ->and($response['error']['data']['requested'])->toBe('1900-01-01');
});

it('does not serve initialize to modern requests', function (): void {
    $response = handleModern(modernMessage('initialize', [
        'protocolVersion' => '2026-07-28',
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'Pest', 'version' => '1.0.0'],
    ]));

    expect($response['error']['code'])->toBe(-32601);
});

it('never negotiates a modern version through the legacy initialize handshake', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);
    $server->start();

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2026-07-28',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'Pest', 'version' => '1.0.0'],
        ],
    ]));

    $response = json_decode((string) $transport->sent[0], true);

    expect($response['result']['protocolVersion'])->toBe(ProtocolVersion::LATEST_LEGACY->value);
});

it('uses the invalid params code for missing resources on modern requests', function (): void {
    $response = handleModern(modernMessage('resources/read', ['uri' => 'file://resources/missing']));

    expect($response['error']['code'])->toBe(-32602);
});

it('keeps the legacy resource-not-found code for legacy requests', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);
    $server->start();

    ($transport->handler)(json_encode([
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => 'resources/read',
        'params' => ['uri' => 'file://resources/missing'],
    ]));

    $response = json_decode((string) $transport->sent[0], true);

    expect($response['error']['code'])->toBe(-32002);
});
