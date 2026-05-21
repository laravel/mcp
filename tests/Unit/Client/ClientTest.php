<?php

declare(strict_types=1);

use Laravel\Mcp\Client;
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
    config(['app.name' => 'Acme MCP App']);

    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();

    $client = new Client($transport);
    $client->connect();

    expect($transport->connected)->toBeTrue();
    expect($client->connected())->toBeTrue();
    expect($client->initializeResult()?->protocolVersion)->toBe(ProtocolVersion::LATEST->value);
    expect($client->initializeResult()?->serverInfo->name)->toBe('Test Server');
    expect($client->initializeResult()?->serverInfo->version)->toBe('1.0.0');
    expect($client->initializeResult()?->capabilities)->toBeArray();
    expect($client->initializeResult())->not->toBeNull();
    expect($client->initializeResult()?->instructions)->toBeNull();

    $initialize = json_decode($transport->sent[0], true);
    expect($initialize['method'])->toBe('initialize');
    expect($initialize['params']['protocolVersion'])->toBe(ProtocolVersion::LATEST->value);
    expect($initialize['params']['clientInfo']['name'])->toBe('Acme MCP App');

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

    expect($client->connected())->toBeFalse();

    $client->ping();

    expect($client->connected())->toBeTrue();
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
    expect($client->connected())->toBeFalse();
});

it('clears connected state when a request fails after the handshake', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = '{not json';

    $client = new Client($transport);
    $client->connect();

    expect($client->connected())->toBeTrue();

    expect(function () use ($client): void {
        $client->ping();
    })->toThrow(ClientException::class, 'Malformed JSON-RPC response from server');

    expect($client->connected())->toBeFalse();
    expect($transport->connected)->toBeFalse();
});

it('keeps the connection open after a JSON-RPC error response', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'error' => ['code' => -32601, 'message' => 'Method not found'],
    ]);

    $client = new Client($transport);
    $client->connect();

    expect(function () use ($client): void {
        $client->ping();
    })->toThrow(JsonRpcException::class, 'Method not found');

    expect($client->connected())->toBeTrue();
    expect($transport->connected)->toBeTrue();
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

it('rethrows JSON-RPC errors and disconnects the transport on handshake failure', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => ['code' => -32600, 'message' => 'Invalid request'],
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(JsonRpcException::class, 'Invalid request');

    expect($transport->connected)->toBeFalse();
    expect($client->connected())->toBeFalse();
});

it('throws when the response payload is malformed JSON', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = '{not json';

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'Malformed JSON-RPC response from server');
});

it('throws when the response is missing the jsonrpc version', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'id' => 1,
        'result' => ['protocolVersion' => '2025-11-25', 'capabilities' => new stdClass, 'serverInfo' => ['name' => 'x', 'version' => 'y']],
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'Invalid JSON-RPC response from server.');
});

it('throws when the response carries both result and error', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [],
        'error' => ['code' => -32600, 'message' => 'nope'],
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'must contain exactly one of "result" or "error"');
});

it('throws when the response carries neither result nor error', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'must contain exactly one of "result" or "error"');
});

it('throws when the error payload is not an object', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'error' => 'not an object',
    ]);

    $client = new Client($transport);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'Invalid JSON-RPC error payload.');
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

it('responds with method-not-found to unsupported server-initiated requests', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = initializeResponse();
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 'sampling-1',
        'method' => 'sampling/createMessage',
        'params' => ['messages' => []],
    ]);
    $transport->responses[] = pingResponse(2);

    $client = new Client($transport);
    $client->ping();

    $errorReply = json_decode($transport->sent[3], true);
    expect($errorReply['id'])->toBe('sampling-1');
    expect($errorReply['error']['code'])->toBe(-32601);
    expect($errorReply['error']['message'])->toContain('sampling/createMessage');
    expect($errorReply)->not->toHaveKey('result');
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

    $result = $client->initializeResult();
    expect($result)->not->toBeNull();
    $info = $result->serverInfo;
    expect($info->name)->toBe('ExampleServer');
    expect($info->title)->toBe('Example Server Display Name');
    expect($info->version)->toBe('1.0.0');
    expect($info->description)->toBe('An example MCP server providing tools and resources');
    expect($info->icons)->toHaveCount(1);
    expect($info->icons[0]->src)->toBe('https://example.com/server-icon.svg');
    expect($info->icons[0]->mimeType)->toBe('image/svg+xml');
    expect($info->icons[0]->sizes)->toBe([]);
    expect($info->icons[0]->theme)->toBeNull();
    expect($info->websiteUrl)->toBe('https://example.com/server');
    expect($result->instructions)->toBe('Optional instructions for the client');
});

it('parses icons with sizes and theme when the server includes them', function (): void {
    $transport = new FakeTransport;
    $transport->responses[] = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'protocolVersion' => ProtocolVersion::LATEST->value,
            'capabilities' => new stdClass,
            'serverInfo' => [
                'name' => 'ExampleServer',
                'version' => '1.0.0',
                'icons' => [[
                    'src' => 'https://example.com/icon.svg',
                    'mimeType' => 'image/svg+xml',
                    'sizes' => ['48x48', '96x96'],
                    'theme' => 'dark',
                ]],
            ],
        ],
    ]);

    $client = new Client($transport);
    $client->connect();

    $icon = $client->initializeResult()?->serverInfo->icons[0] ?? null;
    expect($icon)->not->toBeNull();
    expect($icon->sizes)->toBe(['48x48', '96x96']);
    expect($icon->theme?->value)->toBe('dark');
});

it('times out when a stdio process stays silent', function (): void {
    $client = Client::local(PHP_BINARY, ['-r', 'sleep(1);'])->withTimeout(0.05);

    expect(fn (): Client => $client->connect())
        ->toThrow(ClientException::class, 'Timed out while waiting for server response.');
});
