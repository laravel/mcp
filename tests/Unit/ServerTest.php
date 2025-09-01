<?php

uses(\Laravel\Mcp\Tests\TestCase::class);
use Laravel\Mcp\Tests\Fixtures\ArrayTransport;
use Laravel\Mcp\Tests\Fixtures\CustomMethodHandler;
use Laravel\Mcp\Tests\Fixtures\ExampleServer;

it('can handle an initialize message', function () {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $response = json_decode($transport->sent[0], true);

    expect($response)->toEqual(expectedInitializeResponse());
});

it('can add a capability', function () {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->addCapability('customFeature.enabled', true);
    $server->addCapability('anotherFeature');

    $server->connect($transport);

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $jsonResponse = $transport->sent[0];

    $expectedCapabilitiesJson = json_encode(array_merge((new ExampleServer)->capabilities, [
        'customFeature' => [
            'enabled' => true,
        ],
        'anotherFeature' => (object) [],
    ]));

    $this->assertStringContainsString($expectedCapabilitiesJson, $jsonResponse);
});

it('can handle a list tools message', function () {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(listToolsMessage());

    ($transport->handler)($payload);

    $response = json_decode($transport->sent[0], true);

    expect($response)->toEqual(expectedListToolsResponse());
});

it('can handle a call tool message', function () {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(callToolMessage());

    ($transport->handler)($payload);

    $response = json_decode($transport->sent[0], true);

    expect($response)->toEqual(expectedCallToolResponse());
});

it('can handle a notification message', function () {
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

it('can handle an unknown method', function () {
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

    $response = json_decode($transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 789,
        'error' => [
            'code' => -32601,
            'message' => 'Method not found: unknown/method',
        ],
    ]);
});

it('handles json decode errors', function () {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $invalidJsonPayload = '{"jsonrpc": "2.0", "id": 123, "method": "initialize", "params": {}';

    // Malformed JSON
    ($transport->handler)($invalidJsonPayload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode($transport->sent[0], true);

    expect($response['jsonrpc'])->toEqual('2.0');
    expect($response['id'])->toBeNull();
    expect($response['error']['code'])->toEqual(-32700);
    expect($response['error']['message'])->toEqual('Parse error');
});

it('can handle a custom method message', function () {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->addMethod('custom/method', CustomMethodHandler::class);

    $this->app->bind(CustomMethodHandler::class, fn () => new CustomMethodHandler('custom-dependency'));

    $server->connect($transport);

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 12345,
        'method' => 'custom/method',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode($transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 12345,
        'result' => [
            'message' => 'Custom method executed successfully!',
        ],
    ]);
});

it('can handle a ping message', function () {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(pingMessage());

    ($transport->handler)($payload);

    $response = json_decode($transport->sent[0], true);

    expect($response)->toEqual(expectedPingResponse());
});

it('calls boot method on connect', function () {
    $transport = new ArrayTransport;

    $server = new class extends \Laravel\Mcp\Server
    {
        public function boot()
        {
            $this->bootCalled = true;
        }
    };
    $server->connect($transport);

    expect($server->bootCalled)->toBeTrue('The boot() method was not called on connect.');
});

it('can handle a tool streaming multiple messages', function () {
    $transport = new ArrayTransport;
    $server = new ExampleServer;

    $server->connect($transport);

    $payload = json_encode(callStreamingToolMessage());

    ($transport->handler)($payload);

    $messages = array_map(fn ($msg) => json_decode($msg, true), $transport->sent);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});
