<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Enums\ProtocolEra;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Support\RequestHeaders;

it('mirrors the method and name into headers on a modern call', function (): void {
    Http::fakeSequence()
        ->push(discoverResponse(), 200, ['Content-Type' => 'application/json'])
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['content' => [['type' => 'text', 'text' => 'hi']], 'isError' => false],
        ]), 200, ['Content-Type' => 'application/json']);

    Client::web('https://mcp.test/mcp')->callTool('say-hi', ['name' => 'John']);

    Http::assertSentInOrder([
        function (Request $request): bool {
            expect($request->header(RequestHeaders::PROTOCOL_VERSION)[0])->toBe('2026-07-28');
            expect($request->header(RequestHeaders::METHOD)[0])->toBe('server/discover');
            expect($request->hasHeader('MCP-Session-Id'))->toBeFalse();

            return true;
        },
        function (Request $request): bool {
            expect($request->header(RequestHeaders::METHOD)[0])->toBe('tools/call');
            expect($request->header(RequestHeaders::NAME)[0])->toBe('say-hi');

            return true;
        },
    ]);
});

it('base64 encodes a name that is not header safe', function (): void {
    Http::fakeSequence()
        ->push(discoverResponse(), 200, ['Content-Type' => 'application/json'])
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['contents' => [['uri' => 'file://hello 世界', 'text' => 'hi']]],
        ]), 200, ['Content-Type' => 'application/json']);

    Client::web('https://mcp.test/mcp')->readResource('file://hello 世界');

    Http::assertSent(function (Request $request): bool {
        if ($request->header(RequestHeaders::METHOD)[0] !== 'resources/read') {
            return false;
        }

        return RequestHeaders::decode($request->header(RequestHeaders::NAME)[0]) === 'file://hello 世界';
    });
});

it('reads a modern protocol error out of a 400 response', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32022,
                'message' => 'Unsupported protocol version',
                'data' => ['supported' => ['2027-01-01'], 'requested' => '2026-07-28'],
            ],
        ]), 400, ['Content-Type' => 'application/json']),
    ]);

    expect(fn (): array => Client::web('https://mcp.test/mcp')->tools()->all())
        ->toThrow(JsonRpcException::class, 'Unsupported protocol version');
});

it('falls back to the handshake when the endpoint rejects the modern probe', function (): void {
    Http::fakeSequence()
        ->push('Method Not Allowed', 405)
        ->push(initializeResponse(2), 200, ['Content-Type' => 'application/json'])
        ->push('', 202)
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'result' => ['tools' => [['name' => 'add', 'description' => 'Adds']]],
        ]), 200, ['Content-Type' => 'application/json']);

    $client = Client::web('https://mcp.test/mcp');

    expect($client->tools()->keys()->all())->toBe(['add']);
    expect($client->era())->toBe(ProtocolEra::LEGACY);
});
