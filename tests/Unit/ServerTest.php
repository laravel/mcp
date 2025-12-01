<?php

use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\CustomMethodHandler;
use Tests\Fixtures\ExampleServer;

it('can handle an initialize message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedInitializeResponse());
});

it('can add a capability', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->addCapability('customFeature.enabled', true);
    $server->addCapability('anotherFeature');

    $server->start();

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toHaveKey('result.capabilities');

    $capabilities = $response['result']['capabilities'];

    expect($capabilities)->toHaveKey('customFeature')
        ->and($capabilities['customFeature'])->toBeArray()
        ->and($capabilities['customFeature']['enabled'])->toBeTrue()
        ->and($capabilities)->toHaveKey('anotherFeature')
        ->and($capabilities['anotherFeature'])->toBeArray()
        ->and($capabilities)->toHaveKey('tools')
        ->and($capabilities)->toHaveKey('resources')
        ->and($capabilities)->toHaveKey('prompts');

});

it('can handle a list tools message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(listToolsMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedListToolsResponse());
});

it('can handle a call tool message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(callToolMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedCallToolResponse());
});

it('can handle a notification message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(0);
});

it('can handle an unknown method', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

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
    $server = new ExampleServer($transport);

    $server->start();

    $invalidJsonPayload = '{"jsonrpc": "2.0", "id": 123, "method": "initialize", "params": {}';

    // Malformed JSON
    ($transport->handler)($invalidJsonPayload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toBe([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32700,
            'message' => 'Parse error: Invalid JSON was received by the server.',
        ],
    ]);
});

it('can handle a custom method message', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->addMethod('custom/method', CustomMethodHandler::class);

    $this->app->bind(CustomMethodHandler::class, fn (): \Tests\Fixtures\CustomMethodHandler => new CustomMethodHandler('custom-dependency'));

    $server->start();

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
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(pingMessage());

    ($transport->handler)($payload);

    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual(expectedPingResponse());
});

it('calls boot method on connect', function (): void {
    $transport = new ArrayTransport;

    $server = new class($transport) extends \Laravel\Mcp\Server
    {
        public function boot(): void
        {
            $this->bootCalled = true;
        }
    };
    $server->start();

    expect($server->bootCalled)->toBeTrue('The boot() method was not called on connect.');
});

it('can handle a tool streaming multiple messages', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    $payload = json_encode(callStreamingToolMessage());

    ($transport->handler)($payload);

    $messages = array_map(fn ($msg): mixed => json_decode((string) $msg, true), $transport->sent);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});

it('handles capability with non-array existing value', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    // First set a non-array value
    $server->addCapability('feature');

    // Then try to add a nested capability to it
    $server->addCapability('feature.enabled', true);

    $server->start();

    $payload = json_encode(initializeMessage());

    ($transport->handler)($payload);

    $capabilities = (fn (): array => $this->capabilities)->call($server);

    expect($capabilities['feature'])->toBeArray();
    expect($capabilities['feature']['enabled'])->toBeTrue();
});

it('handles exceptions in debug mode', function (): void {
    config()->set('app.debug', true);

    $transport = new ArrayTransport;
    $server = new class($transport) extends \Laravel\Mcp\Server
    {
        protected array $methods = [
            'test/method' => \Tests\Fixtures\ThrowingMethodHandler::class,
        ];
    };

    $this->app->bind(\Tests\Fixtures\ThrowingMethodHandler::class, fn (): \Laravel\Mcp\Server\Contracts\Method => new class implements \Laravel\Mcp\Server\Contracts\Method
    {
        public function handle(\Laravel\Mcp\Server\Transport\JsonRpcRequest $request, \Laravel\Mcp\Server\ServerContext $context): \Laravel\Mcp\Server\Transport\JsonRpcResponse
        {
            throw new \Exception('Test exception');
        }
    });

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 999,
        'method' => 'test/method',
        'params' => [],
    ]);

    expect(function () use ($transport, $payload): void {
        ($transport->handler)($payload);
    })->toThrow(\Exception::class, 'Test exception');
});

it('handles exceptions in production mode', function (): void {
    config()->set('app.debug', false);

    $transport = new ArrayTransport;
    $server = new class($transport) extends \Laravel\Mcp\Server
    {
        protected array $methods = [
            'test/method' => \Tests\Fixtures\ThrowingMethodHandler::class,
        ];
    };

    $this->app->bind(\Tests\Fixtures\ThrowingMethodHandler::class, fn (): \Laravel\Mcp\Server\Contracts\Method => new class implements \Laravel\Mcp\Server\Contracts\Method
    {
        public function handle(\Laravel\Mcp\Server\Transport\JsonRpcRequest $request, \Laravel\Mcp\Server\ServerContext $context): \Laravel\Mcp\Server\Transport\JsonRpcResponse
        {
            throw new \Exception('Test exception');
        }
    });

    $server->start();

    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => 999,
        'method' => 'test/method',
        'params' => [],
    ]);

    ($transport->handler)($payload);

    expect($transport->sent)->toHaveCount(1);
    $response = json_decode((string) $transport->sent[0], true);

    expect($response)->toEqual([
        'jsonrpc' => '2.0',
        'id' => 999,
        'error' => [
            'code' => -32603,
            'message' => 'Something went wrong while processing the request.',
        ],
    ]);
});
