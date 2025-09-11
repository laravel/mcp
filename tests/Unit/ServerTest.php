<?php

use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\CustomMethodHandler;
use Tests\Fixtures\ExampleServer;

it('can handle an initialize message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedInitializeResponse());
});

it('can add a capability', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->addCapability('customFeature.enabled', true);
    $server->addCapability('anotherFeature');

    $server->connect($transport);

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $jsonResponse = $transport->sent[0];

    $capabilities = (fn (): array => $this->capabilities)->call($server);

    $expectedCapabilitiesJson = json_encode(array_merge($capabilities, [
        'customFeature' => [
            'enabled' => true,
        ],
        'anotherFeature' => (object) [],
    ]));

    $this->assertStringContainsString($expectedCapabilitiesJson, $jsonResponse);
});

it('can handle a list tools message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(listToolsMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedListToolsResponse());
});

it('can handle a call tool message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(callToolMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedCallToolResponse());
});

it('can handle a notification message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(0);
});

it('can handle an unknown method', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 789,
        'method' => 'unknown/method',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 789,
        'error' => [
            'code' => -32601,
            'message' => 'The method [unknown/method] was not found.',
        ],
    ]);
});

it('handles json decode errors', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $invalidJsonPayload = '{"jsonrpc": "2.0", "id": 123, "method": "initialize", "params": {}';

    // Malformed JSON
    ($transport->handler)($invalidJsonPayload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response['jsonrpc'])->toEqual('2.0')
        ->and($response['id'])->toBeNull()
        ->and($response['error']['code'])->toEqual(-32700)
        ->and($response['error']['message'])->toEqual('Parse error.');
});

it('can handle a custom method message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->addMethod('custom/method', CustomMethodHandler::class);

    $this->app->bind(CustomMethodHandler::class, fn (): \Tests\Fixtures\CustomMethodHandler => new CustomMethodHandler('custom-dependency'));

    $server->connect($transport);

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 12345,
        'method' => 'custom/method',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 12345,
        'result' => [
            'message' => 'Custom method executed successfully!',
        ],
    ]);
});

it('can handle a ping message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(pingMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedPingResponse());
});

it('calls boot method on connect', function (): void {
    $transport = new ArrayTransport;

    $server = new class extends \Laravel\Mcp\Server
    {
        public function boot(): void
        {
            $this->bootCalled = true;
        }
    };
    $server->connect($transport);

    expect($server->bootCalled)->toBeTrue('The boot() method was not called on connect.');
});

it('can handle a tool streaming multiple messages', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(callStreamingToolMessage());

    ($transport->handler)($payload);

    $messages = array_map(fn ($msg): mixed => json_decode((string) $msg, true), $transport->sent);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});
