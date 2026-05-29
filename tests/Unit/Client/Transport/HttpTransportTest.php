<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Exceptions\SessionExpiredException;
use Laravel\Mcp\WebClient;

it('posts a frame and queues a json response', function (): void {
    Http::fake([
        'https://mcp.test/mcp' => Http::response(
            json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['ok' => true]]),
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->setTimeoutSeconds(5.0);
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => []]));

    expect(json_decode($transport->receive(), true))->toBe(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['ok' => true]]);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Accept', 'application/json, text/event-stream'));
});

it('parses an SSE stream into individual frames in order', function (): void {
    $stream = sseStream([
        ['jsonrpc' => '2.0', 'method' => 'stream/progress', 'params' => ['progress' => 50]],
        ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['done' => true]],
    ]);

    Http::fake(['*' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream'])]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => []]));

    expect(json_decode($transport->receive(), true))->toMatchArray(['method' => 'stream/progress'])
        ->and(json_decode($transport->receive(), true))->toMatchArray(['id' => 1, 'result' => ['done' => true]]);
});

it('fails fast when the server initiates a request over the SSE stream', function (): void {
    Http::fake(['*' => Http::response(sseStream([
        ['jsonrpc' => '2.0', 'id' => 99, 'method' => 'ping'],
    ]), 200, ['Content-Type' => 'text/event-stream'])]);

    $transport = new HttpTransport('https://mcp.test/mcp');

    expect(function () use ($transport): void {
        $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => []]));
    })->toThrow(ClientException::class, 'does not support');
});

it('skips SSE comments and empty data lines', function (): void {
    $stream = ": ping\n\ndata:\n\n".sseStream([
        ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['ok' => true]],
    ]);

    Http::fake(['*' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream'])]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => []]));

    expect(json_decode($transport->receive(), true))->toMatchArray(['id' => 1, 'result' => ['ok' => true]]);
    expect(fn (): string => $transport->receive())->toThrow(ClientException::class, 'No message available');
});

it('queues nothing for a 202 accepted notification', function (): void {
    Http::fake(['*' => Http::response('', 202)]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));

    expect(fn (): string => $transport->receive())->toThrow(ClientException::class, 'No message available');
});

it('queues nothing when a 200 response has an empty body', function (): void {
    Http::fake(['*' => Http::response('', 200, ['Content-Type' => 'application/json'])]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));

    expect(fn (): string => $transport->receive())->toThrow(ClientException::class, 'No message available');
});

it('captures the MCP-Session-Id and resends it on later requests', function (): void {
    Http::fake([
        '*' => Http::response(
            json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]),
            200,
            ['Content-Type' => 'application/json', 'MCP-Session-Id' => 'session-abc'],
        ),
    ]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]));
    $transport->receive();
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping', 'params' => []]));

    Http::assertSent(fn ($request): bool => ($request['method'] ?? null) === 'initialize' && ! $request->hasHeader('MCP-Session-Id'));
    Http::assertSent(fn ($request): bool => ($request['method'] ?? null) === 'ping' && $request->hasHeader('MCP-Session-Id', 'session-abc'));
});

it('omits the protocol version header on initialize and includes it afterwards', function (): void {
    Http::fake(['*' => Http::response(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]), 200, ['Content-Type' => 'application/json'])]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]));
    $transport->receive();
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']));

    Http::assertSent(fn ($request): bool => ($request['method'] ?? null) === 'initialize' && ! $request->hasHeader('MCP-Protocol-Version'));
    Http::assertSent(fn ($request): bool => ($request['method'] ?? null) === 'tools/list' && $request->hasHeader('MCP-Protocol-Version', ProtocolVersion::LATEST->value));
});

it('sends a bearer Authorization header when a token is set', function (): void {
    Http::fake(['*' => Http::response(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]), 200, ['Content-Type' => 'application/json'])]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->withToken('secret-token');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => []]));

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('throws a ClientException and resets the session on a 404 response', function (): void {
    Http::fakeSequence()
        ->push(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]), 200, ['Content-Type' => 'application/json', 'MCP-Session-Id' => 'session-xyz'])
        ->push('', 404);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]));
    $transport->receive();

    expect(function () use ($transport): void {
        $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping', 'params' => []]));
    })->toThrow(SessionExpiredException::class, 'Session expired');
});

it('treats a 404 without a session id as a normal error rather than session expiry', function (): void {
    Http::fake(['*' => Http::response('', 404)]);

    $transport = new HttpTransport('https://mcp.test/mcp');

    expect(function () use ($transport): void {
        $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => []]));
    })->toThrow(ClientException::class, 'Unexpected HTTP status [404]');
});

it('throws a ClientException on an unexpected HTTP status', function (): void {
    Http::fake(['*' => Http::response('boom', 500)]);

    $transport = new HttpTransport('https://mcp.test/mcp');

    expect(function () use ($transport): void {
        $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => []]));
    })->toThrow(ClientException::class, 'Unexpected HTTP status [500]');
});

it('sends a DELETE with the session id on disconnect', function (): void {
    Http::fake(['*' => Http::response(
        json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]),
        200,
        ['Content-Type' => 'application/json', 'MCP-Session-Id' => 'session-del'],
    )]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]));
    $transport->receive();
    $transport->disconnect();

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && $request->hasHeader('MCP-Session-Id', 'session-del'));
});

it('includes the authorization header on the terminating DELETE', function (): void {
    Http::fake(['*' => Http::response(
        json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]),
        200,
        ['Content-Type' => 'application/json', 'MCP-Session-Id' => 'session-auth'],
    )]);

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->withToken('del-token');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]));
    $transport->receive();
    $transport->disconnect();

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && $request->hasHeader('Authorization', 'Bearer del-token') && $request->hasHeader('MCP-Session-Id', 'session-auth'));
});

it('does not send a DELETE when there is no active session', function (): void {
    Http::fake();

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->disconnect();

    Http::assertNothingSent();
});

it('swallows errors raised while terminating the session on disconnect', function (): void {
    Http::fake(function ($request): PromiseInterface {
        if ($request->method() === 'DELETE') {
            throw new ConnectionException('connection lost');
        }

        return Http::response(
            json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []]),
            200,
            ['Content-Type' => 'application/json', 'MCP-Session-Id' => 'session-boom'],
        );
    });

    $transport = new HttpTransport('https://mcp.test/mcp');
    $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]));
    $transport->receive();
    $transport->disconnect();

    expect(fn (): string => $transport->receive())->toThrow(ClientException::class, 'No message available');
});

it('maps a connection failure to a ClientException', function (): void {
    Http::fake(function (): never {
        throw new ConnectionException('timed out');
    });

    $transport = new HttpTransport('https://mcp.test/mcp');

    expect(function () use ($transport): void {
        $transport->send(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => []]));
    })->toThrow(ClientException::class, 'HTTP request to [https://mcp.test/mcp] failed');
});

it('throws when receiving with no queued message', function (): void {
    $transport = new HttpTransport('https://mcp.test/mcp');

    expect(fn (): string => $transport->receive())->toThrow(ClientException::class, 'No message available');
});

it('drives a full handshake and tools list over HTTP via Client::web', function (): void {
    Http::fakeSequence()
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => ProtocolVersion::LATEST->value,
                'capabilities' => new stdClass,
                'serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
            ],
        ]), 200, ['Content-Type' => 'application/json', 'MCP-Session-Id' => 'session-e2e'])
        ->push('', 202)
        ->push(json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'result' => ['tools' => [['name' => 'add', 'description' => 'Adds two numbers']]],
        ]), 200, ['Content-Type' => 'application/json'])
        ->whenEmpty(Http::response('', 202));

    $tools = Client::web('https://mcp.test/mcp')
        ->withToken('e2e-token')
        ->withTimeout(15)
        ->tools();

    expect($tools->keys()->all())->toBe(['add']);

    Http::assertSent(fn ($request): bool => ($request['method'] ?? null) === 'tools/list' && $request->hasHeader('MCP-Session-Id', 'session-e2e'));
    Http::assertSent(fn ($request): bool => ($request['method'] ?? null) === 'tools/list' && $request->hasHeader('Authorization', 'Bearer e2e-token'));
    Http::assertSent(fn ($request): bool => ($request['method'] ?? null) === 'tools/list' && $request->hasHeader('MCP-Protocol-Version', ProtocolVersion::LATEST->value));
});

it('builds a WebClient from Client::web', function (): void {
    expect(Client::web('https://mcp.test/mcp'))->toBeInstanceOf(WebClient::class);
});

it('re-initializes and retries once after a 404 session expiry', function (): void {
    Http::fakeSequence()
        ->push(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'protocolVersion' => ProtocolVersion::LATEST->value,
            'capabilities' => new stdClass,
            'serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
        ]]), 200, ['Content-Type' => 'application/json', 'MCP-Session-Id' => 'session-1'])
        ->push('', 202)
        ->push('', 404)
        ->push(json_encode(['jsonrpc' => '2.0', 'id' => 3, 'result' => [
            'protocolVersion' => ProtocolVersion::LATEST->value,
            'capabilities' => new stdClass,
            'serverInfo' => ['name' => 'Test Server', 'version' => '1.0.0'],
        ]]), 200, ['Content-Type' => 'application/json', 'MCP-Session-Id' => 'session-2'])
        ->push('', 202)
        ->push(json_encode(['jsonrpc' => '2.0', 'id' => 4, 'result' => [
            'tools' => [['name' => 'add', 'description' => 'Adds two numbers']],
        ]]), 200, ['Content-Type' => 'application/json'])
        ->whenEmpty(Http::response('', 202));

    $tools = Client::web('https://mcp.test/mcp')->tools();

    expect($tools->keys()->all())->toBe(['add']);

    Http::assertSent(fn ($request): bool => ($request['method'] ?? null) === 'tools/list' && $request->hasHeader('MCP-Session-Id', 'session-2'));
});

it('surfaces a 404 during the initialize handshake as a normal error', function (): void {
    Http::fake(['*' => Http::response('', 404)]);

    expect(fn (): WebClient => Client::web('https://mcp.test/mcp')->connect())
        ->toThrow(ClientException::class, 'Unexpected HTTP status [404]');
});
