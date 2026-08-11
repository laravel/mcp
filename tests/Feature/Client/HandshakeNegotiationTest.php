<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Enums\RequestHeader;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Transport\HeaderValue;

it('mirrors the method and name into headers on a discovery era call', function (): void {
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
            expect($request->header(RequestHeader::PROTOCOL_VERSION->value)[0])->toBe('2026-07-28');
            expect($request->header(RequestHeader::METHOD->value)[0])->toBe('server/discover');
            expect($request->hasHeader('MCP-Session-Id'))->toBeFalse();

            return true;
        },
        function (Request $request): bool {
            expect($request->header(RequestHeader::METHOD->value)[0])->toBe('tools/call');
            expect($request->header(RequestHeader::NAME->value)[0])->toBe('say-hi');

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
        if ($request->header(RequestHeader::METHOD->value) === [] || $request->header(RequestHeader::METHOD->value)[0] !== 'resources/read') {
            return false;
        }

        return HeaderValue::fromHeader($request->header(RequestHeader::NAME->value)[0])->matches('file://hello 世界');
    });
});

it('base64 encodes a name ending in a newline', function (): void {
    Http::fakeSequence()
        ->push(discoverResponse(), 200, ['Content-Type' => 'application/json'])
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['contents' => [['uri' => "file://hello\n", 'text' => 'hi']]],
        ]), 200, ['Content-Type' => 'application/json']);

    Client::web('https://mcp.test/mcp')->readResource("file://hello\n");

    Http::assertSent(function (Request $request): bool {
        if ($request->header(RequestHeader::METHOD->value) === [] || $request->header(RequestHeader::METHOD->value)[0] !== 'resources/read') {
            return false;
        }

        $name = $request->header(RequestHeader::NAME->value)[0];

        return str_starts_with($name, '=?base64?')
            && HeaderValue::fromHeader($name)->matches("file://hello\n");
    });
});

it('reads a protocol error out of a 400 response', function (): void {
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

    expect(fn (): array => Client::web('https://mcp.test/mcp')
        ->withProtocolVersion(ProtocolVersion::LATEST)
        ->tools()
        ->all())
        ->toThrow(JsonRpcException::class, 'Unsupported protocol version');

    Http::assertSentCount(1);
});

it('retries with a mutual version from a protocol error in a 400 response', function (): void {
    Http::fakeSequence()
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32022,
                'message' => 'Unsupported protocol version',
                'data' => ['supported' => ['2025-11-25'], 'requested' => '2026-07-28'],
            ],
        ]), 400, ['Content-Type' => 'application/json'])
        ->push(initializeResponse(2), 200, ['Content-Type' => 'application/json'])
        ->push('', 202)
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'result' => ['tools' => []],
        ]), 200, ['Content-Type' => 'application/json']);

    $client = Client::web('https://mcp.test/mcp');

    expect($client->tools()->all())->toBe([]);
    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
});

it('falls back to the handshake when the endpoint rejects a modern request', function (): void {
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
    expect($client->protocolVersion())->toBe(ProtocolVersion::V2025_11_25);
});

it('reports the rejected status alongside a failed fallback', function (): void {
    Http::fakeSequence()
        ->push('Method Not Allowed', 405)
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'error' => ['code' => -32603, 'message' => 'Internal error'],
        ]), 200, ['Content-Type' => 'application/json']);

    expect(fn (): array => Client::web('https://mcp.test/mcp')->tools()->all())
        ->toThrow(
            ClientException::class,
            'The endpoint [https://mcp.test/mcp] rejected the request with HTTP status [405]. The legacy handshake also failed: Internal error',
        );
});

it('does not fall back when the endpoint is unavailable', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response('Bad Gateway', 502),
    ]);

    expect(fn (): array => Client::web('https://mcp.test/mcp')->tools()->all())
        ->toThrow(ClientException::class, 'Unexpected HTTP status [502] from endpoint [https://mcp.test/mcp].');

    Http::assertSentCount(1);
});

it('does not send a protocol version header through the handshake', function (): void {
    Http::fakeSequence()
        ->push('Bad Request', 400)
        ->push(initializeResponse(2), 200, ['Content-Type' => 'application/json'])
        ->push('', 202)
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'result' => ['tools' => []],
        ]), 200, ['Content-Type' => 'application/json']);

    Client::web('https://mcp.test/mcp')->tools();

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        if (($body['method'] ?? null) !== 'initialize') {
            return false;
        }

        return $request->header(RequestHeader::PROTOCOL_VERSION->value) === [];
    });
});
