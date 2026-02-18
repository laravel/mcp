<?php

use Illuminate\Http\Client\Factory;
use Laravel\Mcp\Client\Exceptions\ClientException;
use Laravel\Mcp\Client\Exceptions\ConnectionException;
use Laravel\Mcp\Client\Transport\HttpClientTransport;

it('sends request and returns response body', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/mcp' => $http->response('{"jsonrpc":"2.0","id":1,"result":{}}', 200),
    ]);

    $transport = new HttpClientTransport('https://example.com/mcp', [], 30, $http);
    $transport->connect();

    $response = $transport->send('{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}');

    expect($response)->toBe('{"jsonrpc":"2.0","id":1,"result":{}}');
});

it('tracks session id from response headers', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/mcp' => $http->response('{"jsonrpc":"2.0","id":1,"result":{}}', 200, [
            'MCP-Session-Id' => 'session-abc',
        ]),
    ]);

    $transport = new HttpClientTransport('https://example.com/mcp', [], 30, $http);
    $transport->connect();

    $transport->send('{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}');

    expect($transport->isConnected())->toBeTrue();
});

it('throws on failed http response', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/mcp' => $http->response('Internal Server Error', 500),
    ]);

    $transport = new HttpClientTransport('https://example.com/mcp', [], 30, $http);
    $transport->connect();

    $transport->send('{"jsonrpc":"2.0","id":1,"method":"ping","params":{}}');
})->throws(ClientException::class, 'HTTP request failed with status 500.');

it('throws when sending while disconnected', function (): void {
    $http = new Factory;
    $transport = new HttpClientTransport('https://example.com/mcp', [], 30, $http);

    $transport->send('message');
})->throws(ConnectionException::class, 'Not connected.');

it('sends notifications', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/mcp' => $http->response('', 202),
    ]);

    $transport = new HttpClientTransport('https://example.com/mcp', [], 30, $http);
    $transport->connect();

    $transport->notify('{"jsonrpc":"2.0","method":"notifications/initialized"}');

    expect($transport->isConnected())->toBeTrue();
});

it('manages connection state', function (): void {
    $http = new Factory;
    $transport = new HttpClientTransport('https://example.com/mcp', [], 30, $http);

    expect($transport->isConnected())->toBeFalse();

    $transport->connect();
    expect($transport->isConnected())->toBeTrue();

    $transport->disconnect();
    expect($transport->isConnected())->toBeFalse();
});

it('sends delete on disconnect when session id exists', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/mcp' => $http->sequence()
            ->push('{"jsonrpc":"2.0","id":1,"result":{}}', 200, ['MCP-Session-Id' => 'session-123'])
            ->push('', 200),
    ]);

    $transport = new HttpClientTransport('https://example.com/mcp', [], 30, $http);
    $transport->connect();

    $transport->send('{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}');

    $transport->disconnect();

    $http->assertSentCount(2);
    $http->assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && $request->hasHeader('MCP-Session-Id', 'session-123'));
});

it('does not send delete on disconnect without session id', function (): void {
    $http = new Factory;
    $http->fake();

    $transport = new HttpClientTransport('https://example.com/mcp', [], 30, $http);
    $transport->connect();
    $transport->disconnect();

    $http->assertNothingSent();
});

it('includes custom headers', function (): void {
    $http = new Factory;
    $http->fake([
        'example.com/mcp' => $http->response('{"jsonrpc":"2.0","id":1,"result":{}}', 200),
    ]);

    $transport = new HttpClientTransport('https://example.com/mcp', ['Authorization' => 'Bearer test-token'], 30, $http);
    $transport->connect();

    $transport->send('{"jsonrpc":"2.0","id":1,"method":"ping"}');

    $http->assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request->hasHeader('Accept', 'application/json, text/event-stream'));
});
